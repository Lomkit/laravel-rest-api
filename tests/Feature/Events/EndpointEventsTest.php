<?php

namespace Lomkit\Rest\Tests\Feature\Events;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class EndpointEventsTest extends HelpdeskTestCase
{
    private const array OPEN = ['filters' => [['field' => 'status', 'value' => 'open']]];

    private const array TITLE_ONLY = ['filters' => [['field' => 'title', 'value' => 'Printer jam']]];

    /** @var array<string, int> */
    private array $ids = [];

    /** @var array<string, int> */
    private array $teamIds = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EventLog::reset();
        Rest::resource('event-tickets', EventTicketController::class)->register();
        $rows = $this->seedTickets();
        $this->ids = array_map(fn (Ticket $ticket): int => $ticket->getKey(), $rows);
        $this->teamIds = array_map(fn (Ticket $ticket): int => $ticket->getAttribute('team_id'), $rows);
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));
        EventLog::listen();
    }

    #[Test]
    public function it_runs_describing_and_described_around_the_details(): void
    {
        $response = $this->getJson('/event-tickets');

        $response->assertOk();
        $this->assertSame(['describing', 'described actions,metrics,instructions,fields,limits,scopes,relations,rules,text,gates,authorizations'], EventLog::$lines);
        $this->assertSame(['viewAny', 'create'], $this->tickets->calls);
    }

    #[Test]
    public function it_runs_searching_before_the_query_and_searched_after_it(): void
    {
        $response = $this->postJson('/event-tickets/search', ['search' => [...self::OPEN, 'includes' => [['relation' => 'team']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [$this->ids['Printer toner'], $this->ids['Printer jam']]);
        $this->assertSame([
            'searching',
            'sql select tickets',
            'sql select teams',
            "searched {$this->ids['Printer toner']},{$this->ids['Printer jam']} of 2",
        ], EventLog::$lines);
        $this->assertInstanceOf(SearchContext::class, EventLog::$payloads[0]);
        $this->assertTrue(EventLog::$payloads[0]->filtersOn('status'));
    }

    /** @return array<string, array{string}> */
    public static function searches(): array
    {
        return [
            'the search endpoint' => ['/event-tickets/search'],
            'the search of an action' => ['/event-tickets/actions/close-event-tickets'],
        ];
    }

    #[Test]
    #[DataProvider('searches')]
    public function it_answers_422_before_any_query_when_searching_refuses_the_search(string $uri): void
    {
        $response = $this->postJson($uri, ['search' => self::TITLE_ONLY]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The title filter requires a status filter.',
            'errors' => ['search.filters' => ['The title filter requires a status filter.']],
        ]);
        $this->assertSame(['searching'], EventLog::$lines);
        $this->assertSame('open', Ticket::query()->whereKey($this->ids['Printer jam'])->value('status'));
    }

    #[Test]
    public function it_runs_the_mutate_events_inside_the_transaction(): void
    {
        $response = $this->postJson('/event-tickets/mutate', [
            'mutate' => [
                ['operation' => 'create', 'attributes' => ['team_id' => $this->teamIds['Printer jam'], 'title' => 'Fan noise', 'status' => 'open']],
                ['operation' => 'update', 'key' => $this->ids['Printer toner'], 'attributes' => ['title' => 'Toner low']],
            ],
            'response' => [],
        ]);

        $created = $response->json('operations.0.key');
        $response->assertOk()->assertJsonPath('operations', [
            ['operation' => 'create', 'key' => $created],
            ['operation' => 'update', 'key' => $this->ids['Printer toner']],
        ]);
        $this->assertSame([
            'sql select tickets',
            'begin level 2',
            'mutatingAll create,update',
            'sql insert tickets',
            'sql update tickets',
            'mutatedAll [{"operation":"create","key":'.$created.'},{"operation":"update","key":'.$this->ids['Printer toner'].'}]',
            'commit level 1',
            'sql select tickets',
        ], EventLog::$lines);
    }

    #[Test]
    public function it_answers_422_and_writes_nothing_when_mutating_all_refuses_the_entries(): void
    {
        $response = $this->postJson('/event-tickets/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['team_id' => $this->teamIds['Printer jam'], 'title' => 'Spam', 'status' => 'open']],
        ]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'Spam tickets are refused.',
            'errors' => ['mutate' => ['Spam tickets are refused.']],
        ]);
        $this->assertSame(['begin level 2', 'mutatingAll create', 'rollback level 1'], EventLog::$lines);
        $this->assertSame(4, Ticket::query()->count());
    }

    #[Test]
    public function it_rolls_the_mutation_back_when_mutated_all_throws(): void
    {
        EventLog::$failure = 'mutatedAll';

        $response = $this->postJson('/event-tickets/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['team_id' => $this->teamIds['Printer jam'], 'title' => 'Mouse', 'status' => 'open']],
        ]]);

        $response->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);
        [$insert, $event, $rollback] = array_slice(EventLog::$lines, -3);
        $this->assertSame(['sql insert tickets', 'rollback level 1'], [$insert, $rollback]);
        $this->assertStringStartsWith('mutatedAll [{"operation":"create","key":', $event);
        $this->assertFalse(Ticket::query()->where('title', 'Mouse')->exists());
    }

    #[Test]
    public function it_runs_the_operate_events_around_the_chunks_and_before_respond(): void
    {
        $response = $this->postJson('/event-tickets/actions/close-event-tickets', ['search' => self::OPEN]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([
            'searching',
            'sql select tickets',
            'operating close-event-tickets',
            'sql select tickets',
            'begin level 2',
            "handle {$this->ids['Printer jam']}",
            'sql update tickets',
            'commit level 1',
            'sql select tickets',
            'begin level 2',
            "handle {$this->ids['Printer toner']}",
            'sql update tickets',
            'commit level 1',
            'sql select tickets',
            'operated close-event-tickets 2',
            'respond 2',
        ], EventLog::$lines);
    }

    #[Test]
    public function it_runs_the_operate_events_inside_the_transaction_of_an_atomic_action(): void
    {
        $response = $this->postJson('/event-tickets/actions/atomic-close-event-tickets', ['search' => self::OPEN]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([
            'searching',
            'sql select tickets',
            'begin level 2',
            'operating atomic-close-event-tickets',
            'sql select tickets',
            "handle {$this->ids['Printer jam']}",
            'sql update tickets',
            'sql select tickets',
            "handle {$this->ids['Printer toner']}",
            'sql update tickets',
            'sql select tickets',
            'operated atomic-close-event-tickets 2',
            'commit level 1',
            'respond 2',
        ], EventLog::$lines);
    }

    /** @return array<string, array{string, list<string>, int}> */
    public static function failedActions(): array
    {
        return [
            'atomic, every chunk rolled back' => ['atomic-close-event-tickets', ['sql select tickets', 'operated atomic-close-event-tickets 2', 'rollback level 1'], 2],
            'one transaction per chunk, the chunks stay written' => ['close-event-tickets', ['commit level 1', 'sql select tickets', 'operated close-event-tickets 2'], 0],
        ];
    }

    /** @param list<string> $tail */
    #[Test]
    #[DataProvider('failedActions')]
    public function it_answers_the_error_of_operated_without_responding(string $action, array $tail, int $open): void
    {
        EventLog::$failure = 'operated';

        $response = $this->postJson("/event-tickets/actions/{$action}", ['search' => self::OPEN]);

        $response->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);
        $this->assertSame($tail, array_slice(EventLog::$lines, -3));
        $this->assertSame($open, Ticket::query()->where('status', 'open')->count());
    }

    #[Test]
    public function it_passes_one_action_fields_object_to_the_events_handle_and_respond(): void
    {
        $this->postJson('/event-tickets/actions/close-event-tickets', ['search' => self::OPEN])->assertOk();

        $search = EventLog::$payloads[0];
        $fields = array_slice(EventLog::$payloads, 1);

        $this->assertInstanceOf(SearchContext::class, $search);
        $this->assertCount(5, $fields);
        $this->assertInstanceOf(ActionFields::class, $fields[0]);
        $this->assertSame([$fields[0], $fields[0], $fields[0], $fields[0], $fields[0]], $fields);
    }

    #[Test]
    public function it_runs_the_operate_events_of_an_action_without_rows(): void
    {
        $response = $this->postJson('/event-tickets/actions/report-outage');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->assertSame(['operating report-outage', 'begin level 2', 'commit level 1', 'operated report-outage 0'], EventLog::$lines);
    }

    #[Test]
    public function it_runs_operated_once_the_jobs_are_queued(): void
    {
        Queue::fake();

        $response = $this->postJson('/event-tickets/actions/queued-close-event-tickets', ['search' => self::OPEN]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([
            'searching',
            'sql select tickets',
            'operating queued-close-event-tickets',
            'sql select tickets',
            'sql select tickets',
            'sql select tickets',
            'operated queued-close-event-tickets 2',
            'respond 2',
        ], EventLog::$lines);
        Queue::assertPushed(QueuedAction::class, 2);
    }

    #[Test]
    public function it_runs_the_destroy_events_inside_the_transaction(): void
    {
        $response = $this->deleteJson('/event-tickets', ['resources' => [$this->ids['Laptop setup'], $this->ids['Password reset']]]);

        $response->assertOk()->assertJsonPath('data.*.id', [$this->ids['Laptop setup'], $this->ids['Password reset']]);
        $this->assertSame([
            'sql select tickets',
            'begin level 2',
            "destroyingAll {$this->ids['Laptop setup']},{$this->ids['Password reset']}",
            "destroying {$this->ids['Laptop setup']}",
            'sql delete tickets',
            "destroying {$this->ids['Password reset']}",
            'sql delete tickets',
            "destroyedAll {$this->ids['Laptop setup']},{$this->ids['Password reset']}",
            'commit level 1',
        ], EventLog::$lines);
    }

    #[Test]
    public function it_rolls_the_deletion_back_when_destroyed_all_throws(): void
    {
        EventLog::$failure = 'destroyedAll';

        $response = $this->deleteJson('/event-tickets', ['resources' => [$this->ids['Printer jam']]]);

        $response->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);
        $this->assertSame(["destroyedAll {$this->ids['Printer jam']}", 'rollback level 1'], array_slice(EventLog::$lines, -2));
        $this->assertTrue(Ticket::query()->whereKey($this->ids['Printer jam'])->exists());
    }

    #[Test]
    public function it_runs_no_destroy_event_when_a_row_is_refused(): void
    {
        $this->actingAs(new GenericUser(['id' => 7]));

        $response = $this->deleteJson('/event-tickets', ['resources' => [$this->ids['Printer jam']]]);

        $response->assertForbidden()->assertExactJson(['message' => 'Only the helpdesk lead deletes tickets.']);
        $this->assertSame(['sql select tickets'], EventLog::$lines);
    }
}

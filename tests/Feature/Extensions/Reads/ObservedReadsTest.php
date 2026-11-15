<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Reads;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\MissingColumnException;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ChangingReadObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ChangingReadObserverNamedFirst;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FailingReadObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ObservedTicketController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ObservedTicketResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OpenTicketCount;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingReadObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingReadObserverNamedSecond;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TeamReadingObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WriteLog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class ObservedReadsTest extends HelpdeskTestCase
{
    use ResetsExtensionStubs;

    private const array SEARCH = ['search' => [
        'filters' => [['field' => 'status', 'value' => 'open']],
        'includes' => [['relation' => 'team']],
        'sorts' => [['field' => 'id', 'direction' => 'asc']],
        'limit' => 10,
    ]];

    private const array DETAILS = ['data' => [
        'fields' => [
            ['name' => 'id', 'label' => null, 'type' => 'id', 'nullable' => false],
            ['name' => 'team_id', 'label' => null, 'type' => 'integer', 'nullable' => false],
            ['name' => 'title', 'label' => null, 'type' => 'text', 'nullable' => false],
            ['name' => 'status', 'label' => null, 'type' => 'text', 'nullable' => false],
        ],
        'limits' => [10, 25, 50],
        'meta' => ['plan' => 'pro'],
    ]];

    private GenericUser $lead;

    /** @var array<string, int> */
    private array $ids = [];

    private int $billing;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-08 10:00:00');
        $this->ids = array_map(fn (Ticket $ticket): int => $ticket->getKey(), $this->seedTickets());
        $this->billing = Team::query()
            ->where('name', 'Billing')
            ->value('id');
        Rest::resource('observed-tickets', ObservedTicketController::class)->register();
        $this->lead = new GenericUser(['id' => TicketPolicy::LEAD]);
        $this->actingAs($this->lead);
        config(['rest.extensions' => [RecordingReadObserver::class]]);
    }

    #[Test]
    public function it_notifies_a_search_after_its_page_is_computed(): void
    {
        $this->postJson('/observed-tickets/search', self::SEARCH)->assertOk()->assertExactJson($this->page());

        $this->assertSame(['searching', "searched {$this->ids['Printer jam']},{$this->ids['Printer toner']} of 2", "read search {$this->ids['Printer jam']} Printer jam,{$this->ids['Printer toner']} Printer toner of 2"], EventLog::$lines);
        $context = WriteLog::$reads[0];
        $this->assertCount(1, WriteLog::$reads);
        $this->assertInstanceOf(ObservedTicketResource::class, $context->resource);
        $this->assertSame(Endpoint::Search, $context->endpoint);
        $this->assertSame($this->lead, $context->user);
        $this->assertSame([2, 1, 10], [$context->total, $context->page, $context->perPage]);
        $this->assertSame([[$this->ids['Printer jam'], 'Billing'], [$this->ids['Printer toner'], 'Billing']], array_map(fn (Model $row): array => [$row->getKey(), $row->getRelation('team')?->getAttribute('name')], $context->rows));
        $this->assertNull($context->metric);
        $this->assertNull($context->result);
        $this->assertNull($context->details);
    }

    /** @return array<string, array{string, bool}> */
    public static function programmaticSearches(): array
    {
        return [
            'page' => ['page', true],
            'render' => ['render', true],
            'lazy' => ['lazy', false],
        ];
    }

    #[Test]
    #[DataProvider('programmaticSearches')]
    public function it_notifies_no_read_observer_of_rest_search(string $method, bool $paged): void
    {
        $result = Rest::search(ObservedTicketResource::class)->as(new GenericUser(['id' => 7]))->payload(self::SEARCH['search'])->{$method}();

        if ($method === 'lazy') {
            $this->assertSame([$this->ids['Printer jam'], $this->ids['Printer toner']], array_column($result->all(), 'id'));
        }

        $searched = "searched {$this->ids['Printer jam']},{$this->ids['Printer toner']} of 2";
        $this->assertSame($paged ? ['searching', $searched] : ['searching'], EventLog::$lines);
        $this->assertSame([], WriteLog::$reads);
    }

    #[Test]
    public function it_notifies_a_metric_computed_and_read_from_the_cache(): void
    {
        $first = $this->postJson('/observed-tickets/metrics/open-ticket-count')->assertOk()->getContent();
        $second = $this->postJson('/observed-tickets/metrics/open-ticket-count')->assertOk()->getContent();

        $this->assertSame(['{"data":{"type":"value","value":2,"previous":null}}', '{"data":{"type":"value","value":2,"previous":null}}'], [$first, $second]);
        $this->assertSame(['searching', 'calculate', 'read metric {"type":"value","value":2,"previous":null}', 'searching', 'read metric {"type":"value","value":2,"previous":null}'], EventLog::$lines);
        $this->assertCount(2, WriteLog::$reads);

        foreach (WriteLog::$reads as $context) {
            $this->assertSame(Endpoint::Metric, $context->endpoint);
            $this->assertInstanceOf(OpenTicketCount::class, $context->metric);
            $this->assertSame(['type' => 'value', 'value' => 2, 'previous' => null], $context->result);
            $this->assertNull($context->rows);
        }
    }

    #[Test]
    public function it_notifies_details_after_described(): void
    {
        $this->getJson('/observed-tickets')->assertOk()->assertExactJson(self::DETAILS);

        $this->assertSame(['describing', 'described fields,limits,meta', 'read details fields,limits,meta'], EventLog::$lines);
        $this->assertSame(self::DETAILS['data'], WriteLog::$reads[0]->details);
        $this->assertSame(Endpoint::Details, WriteLog::$reads[0]->endpoint);
        $this->assertNull(WriteLog::$reads[0]->rows);
    }

    #[Test]
    public function it_hands_an_empty_list_for_a_search_without_rows(): void
    {
        $this->postJson('/observed-tickets/search', ['search' => ['filters' => [['field' => 'status', 'value' => 'archived']]]])->assertOk();

        $this->assertCount(1, WriteLog::$reads);
        $this->assertSame([], WriteLog::$reads[0]->rows);
        $this->assertSame(0, WriteLog::$reads[0]->total);
    }

    /** @return array<string, array{string, string, array<string, mixed>, bool}> */
    public static function reads(): array
    {
        return [
            'search' => ['POST', '/observed-tickets/search', self::SEARCH, false],
            'details' => ['GET', '/observed-tickets', [], true],
            'metric' => ['POST', '/observed-tickets/metrics/open-ticket-count', [], true],
        ];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('reads')]
    public function it_keeps_every_response_when_an_observer_changes_its_copies(string $method, string $uri, array $body, bool $readonly): void
    {
        config(['rest.extensions' => []]);
        $plain = $this->json($method, $uri, $body)->assertOk()->getContent();
        $this->app['cache']->flush();
        config(['rest.extensions' => [ChangingReadObserver::class]]);

        $changed = $this->json($method, $uri, $body)->assertOk()->getContent();

        $this->assertSame($plain, $changed);
        $this->assertSame($readonly ? ['read readonly'] : [], array_values(array_filter(EventLog::$lines, fn (string $line): bool => str_starts_with($line, 'read'))));
        $this->assertSame(['Billing', 'Support'], Team::query()->orderBy('id')->pluck('name')->all());
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('reads')]
    public function it_lets_a_read_observer_exception_reach_the_client(string $method, string $uri, array $body, bool $readonly): void
    {
        config(['rest.extensions' => [FailingReadObserver::class]]);
        $this->withoutExceptionHandling();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The read log is offline.');

        $this->json($method, $uri, $body);
    }

    #[Test]
    public function it_hands_each_extension_its_own_copies(): void
    {
        config(['rest.extensions' => [ChangingReadObserverNamedFirst::class, RecordingReadObserverNamedSecond::class]]);

        $this->postJson('/observed-tickets/search', self::SEARCH)->assertOk()->assertExactJson($this->page());

        $this->assertSame(["read search {$this->ids['Printer jam']} Printer jam,{$this->ids['Printer toner']} Printer toner of 2"], array_values(array_filter(EventLog::$lines, fn (string $line): bool => str_starts_with($line, 'read'))));
        $this->assertSame([['Billing'], ['Billing']], array_map(fn (Model $row): array => [$row->getRelation('team')?->getAttribute('name')], WriteLog::$reads[0]->rows));
    }

    /** @return array<string, array{list<class-string>, list<string>}> */
    public static function configuredOrders(): array
    {
        $changingFirst = ['read readonly', 'read details fields,limits,meta'];

        return [
            'in name order' => [[ChangingReadObserverNamedFirst::class, RecordingReadObserverNamedSecond::class], $changingFirst],
            'against name order' => [[RecordingReadObserverNamedSecond::class, ChangingReadObserverNamedFirst::class], $changingFirst],
        ];
    }

    /**
     * @param  list<class-string>  $extensions
     * @param  list<string>  $lines
     */
    #[Test]
    #[DataProvider('configuredOrders')]
    public function it_notifies_the_read_observers_in_name_order(array $extensions, array $lines): void
    {
        config(['rest.extensions' => $extensions]);

        $this->getJson('/observed-tickets')->assertOk()->assertExactJson(self::DETAILS);

        $this->assertSame($lines, array_values(array_filter(EventLog::$lines, fn (string $line): bool => str_starts_with($line, 'read'))));
    }

    #[Test]
    public function it_refuses_a_read_observer_that_reads_a_column_that_the_page_did_not_load(): void
    {
        config(['rest.extensions' => [TeamReadingObserver::class]]);

        $this->postJson('/observed-tickets/search', self::SEARCH)->assertOk()->assertExactJson($this->page());
        $narrowed = $this->postJson('/observed-tickets/search', ['search' => ['selects' => [['field' => 'title']], 'sorts' => [['field' => 'id', 'direction' => 'asc']]]]);

        $narrowed->assertStatus(500);
        $this->assertInstanceOf(MissingColumnException::class, $narrowed->exception);
        $this->assertSame(["read team {$this->billing}", "read team {$this->billing}"], array_values(array_filter(EventLog::$lines, fn (string $line): bool => str_starts_with($line, 'read'))));
    }

    #[Test]
    public function it_hands_the_column_of_a_hidden_field_to_a_read_observer_without_rendering_it(): void
    {
        MaskingFields::$narrow = fn (FieldsContext $context) => $context->hide('status');
        config(['rest.extensions' => [MaskingFields::class, RecordingReadObserver::class]]);

        $response = $this->postJson('/observed-tickets/search', ['search' => ['filters' => [['field' => 'id', 'value' => $this->ids['Printer jam']]]]])->assertOk();

        $this->assertSame([['id' => $this->ids['Printer jam'], 'team_id' => $this->billing, 'title' => 'Printer jam']], $response->json('data'));
        $this->assertSame([[$this->ids['Printer jam'], 'open']], array_map(fn (Model $row): array => [$row->getKey(), $row->getAttribute('status')], WriteLog::$reads[0]->rows ?? []));
    }

    /** @return array<string, mixed> */
    private function page(): array
    {
        $team = ['id' => $this->billing, 'name' => 'Billing'];

        return [
            'current_page' => 1,
            'data' => [
                ['id' => $this->ids['Printer jam'], 'status' => 'open', 'team' => $team, 'team_id' => $this->billing, 'title' => 'Printer jam'],
                ['id' => $this->ids['Printer toner'], 'status' => 'open', 'team' => $team, 'team_id' => $this->billing, 'title' => 'Printer toner'],
            ],
            'from' => 1,
            'last_page' => 1,
            'meta' => [],
            'per_page' => 10,
            'to' => 2,
            'total' => 2,
        ];
    }
}

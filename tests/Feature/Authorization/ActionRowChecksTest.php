<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\CachedReviewedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\ReviewAllowedTickets;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\ReviewedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\ReviewedTicketResource;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\ReviewTickets;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ActionRowChecksTest extends HelpdeskTestCase
{
    private const array CHUNKS = [[1, 2], [3, 4], [5, 6], [7]];

    private const array UPDATES = ['viewAny', 'update 1', 'update 2', 'update 3', 'update 4', 'update 5', 'update 6', 'update 7'];

    private const string READ_ONLY = 'Closed tickets are read-only.';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ReviewTickets::reset();
        Rest::resource('reviewed-tickets', ReviewedTicketController::class)->register();
        Team::create(['id' => 1, 'name' => 'Support']);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<int>  $closed
     * @param  list<array{path: string, ability: string, key: int, message: string}>  $denials
     */
    #[Test]
    #[DataProvider('refusals')]
    public function it_refuses_the_action_when_a_row_is_refused(ActionTarget $target, string $ability, array $body, array $closed, int $status, array $denials): void
    {
        $this->renderDenials();
        $this->seedRows($closed);
        ReviewedTicketResource::$target = $target;
        ReviewTickets::$ability = $ability;

        $response = $this->postJson('/reviewed-tickets/actions/review-tickets', $body);

        $response->assertStatus($status)->assertExactJson(['message' => $denials[0]['message'], 'denials' => $denials]);
        $this->assertSame([], ReviewTickets::$runs);
    }

    /** @return array<string, array{ActionTarget, string, array<string, mixed>, list<int>, int, list<array{path: string, ability: string, key: int, message: string}>}> */
    public static function refusals(): array
    {
        $lead = 'Only the helpdesk lead deletes tickets.';

        return [
            'a named row' => [ActionTarget::Resources, 'update', ['resources' => [5, 2, 7]], [2], 423, [
                ['path' => 'resources.1', 'ability' => 'update', 'key' => 2, 'message' => self::READ_ONLY],
            ]],
            'two named rows' => [ActionTarget::Resources, 'update', ['resources' => [6, 2, 7]], [2, 6], 423, [
                ['path' => 'resources.0', 'ability' => 'update', 'key' => 6, 'message' => self::READ_ONLY],
                ['path' => 'resources.1', 'ability' => 'update', 'key' => 2, 'message' => self::READ_ONLY],
            ]],
            'rows of the search in two chunks' => [ActionTarget::Search, 'update', ['search' => []], [2, 6], 423, [
                ['path' => 'search', 'ability' => 'update', 'key' => 2, 'message' => self::READ_ONLY],
                ['path' => 'search', 'ability' => 'update', 'key' => 6, 'message' => self::READ_ONLY],
            ]],
            'a plain refusal' => [ActionTarget::Search, 'delete', ['search' => ['filters' => [['field' => 'title', 'value' => 'Ticket 3']]]], [], 403, [
                ['path' => 'search', 'ability' => 'delete', 'key' => 3, 'message' => $lead],
            ]],
        ];
    }

    #[Test]
    public function it_checks_every_chunk_before_the_first_row_is_handled(): void
    {
        $this->seedRows([2]);
        $this->logQueries();

        $response = $this->postJson('/reviewed-tickets/actions/review-tickets', ['search' => []]);

        $response->assertStatus(423)->assertExactJson(['message' => self::READ_ONLY]);
        $this->assertSame([], ReviewTickets::$runs);
        $this->assertSame(self::UPDATES, $this->tickets->calls);
        $this->assertSame(4, $this->ticketSelects());
    }

    #[Test]
    public function it_reads_the_rows_again_to_run_the_action_once_every_row_is_allowed(): void
    {
        $this->seedRows();
        $this->logQueries();

        $response = $this->postJson('/reviewed-tickets/actions/review-tickets', ['search' => []]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 7, 'result' => null]]);
        $this->assertSame(self::CHUNKS, ReviewTickets::$runs);
        $this->assertSame(self::UPDATES, $this->tickets->calls);
        $this->assertSame(8, $this->ticketSelects());
    }

    /**
     * @param  list<int>  $closed
     * @param  list<list<int>>  $runs
     */
    #[Test]
    #[DataProvider('skippedRows')]
    public function it_runs_the_action_on_the_allowed_rows_only(array $closed, array $runs, int $impacted): void
    {
        $this->seedRows($closed);
        $this->logQueries();

        $response = $this->postJson('/reviewed-tickets/actions/review-allowed-tickets', ['search' => []]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted, 'result' => null]]);
        $this->assertSame($runs, ReviewTickets::$runs);
        $this->assertSame(self::UPDATES, $this->tickets->calls);
        $this->assertSame(4, $this->ticketSelects());
    }

    /** @return array<string, array{list<int>, list<list<int>>, int}> */
    public static function skippedRows(): array
    {
        return [
            'refused rows in two chunks' => [[2, 6], [[1], [3, 4], [5], [7]], 5],
            'a chunk without an allowed row' => [[3, 4], [[1, 2], [5, 6], [7]], 5],
            'every row refused' => [[1, 2, 3, 4, 5, 6, 7], [], 0],
        ];
    }

    /**
     * @param  list<int>  $closed
     * @param  list<list<int>>  $runs
     */
    #[Test]
    #[DataProvider('rowsPerTransaction')]
    public function it_checks_the_rows_of_an_action_that_runs_each_row_in_its_own_transaction(string $action, array $closed, int $status, array $runs): void
    {
        $this->seedRows($closed);

        $response = $this->postJson("/reviewed-tickets/actions/{$action}", ['search' => []]);

        $response->assertStatus($status);
        $this->assertSame($runs, ReviewTickets::$runs);
        $this->assertSame(self::UPDATES, $this->tickets->calls);
    }

    /** @return array<string, array{string, list<int>, int, list<list<int>>}> */
    public static function rowsPerTransaction(): array
    {
        return [
            'every row allowed' => ['review-tickets-per-row', [], 200, [[1], [2], [3], [4], [5], [6], [7]]],
            'a refused row' => ['review-tickets-per-row', [2, 6], 423, []],
            'refused rows skipped' => ['review-allowed-tickets-per-row', [2, 6], 200, [[1], [3], [4], [5], [7]]],
            'refused rows skipped in the jobs' => ['queued-review-allowed-tickets-per-row', [2, 6], 200, [[1], [3], [4], [5], [7]]],
        ];
    }

    /** @param list<string> $calls */
    #[Test]
    #[DataProvider('rechecks')]
    public function it_drops_the_decisions_of_a_chunk_once_it_is_checked(string $action, array $calls): void
    {
        $this->seedRows();
        ReviewTickets::$recheck = true;

        $this->postJson("/reviewed-tickets/actions/{$action}", ['search' => []])->assertOk();

        $this->assertSame($calls, $this->tickets->calls);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function rechecks(): array
    {
        return [
            'before the rows are handled' => ['review-tickets', [...self::UPDATES, 'update 1', 'update 2', 'update 3', 'update 4', 'update 5', 'update 6', 'update 7']],
            'before the allowed rows are handled' => ['review-allowed-tickets', ['viewAny', 'update 1', 'update 2', 'update 1', 'update 2', 'update 3', 'update 4', 'update 3', 'update 4', 'update 5', 'update 6', 'update 5', 'update 6', 'update 7', 'update 7']],
        ];
    }

    #[Test]
    public function it_checks_the_rows_before_any_job_is_queued(): void
    {
        Queue::fake();
        $this->seedRows([2]);

        $this->postJson('/reviewed-tickets/actions/queued-review-tickets', ['search' => []])->assertStatus(423);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_queues_the_allowed_rows_only(): void
    {
        Queue::fake();
        $this->seedRows([2, 6]);

        $response = $this->postJson('/reviewed-tickets/actions/queued-review-allowed-tickets', ['search' => []]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 5, 'result' => null]]);
        $this->assertSame([[1], [3, 4], [5], [7]], Queue::pushed(QueuedAction::class)->map(fn (QueuedAction $job): array => $job->rows->keys)->all());
    }

    #[Test]
    public function it_checks_the_rows_before_the_batch_is_built(): void
    {
        Bus::fake();
        $this->seedRows([2]);

        $this->postJson('/reviewed-tickets/actions/batched-review-tickets', ['search' => []])->assertStatus(423);

        Bus::assertNothingBatched();
        $this->assertFalse(ReviewTickets::$batched);
    }

    #[Test]
    public function it_does_not_check_the_rows_again_in_the_worker(): void
    {
        Queue::fake();
        $this->seedRows();
        $this->postJson('/reviewed-tickets/actions/queued-review-tickets', ['search' => []])->assertOk();
        $job = Queue::pushed(QueuedAction::class)->first();
        Ticket::query()->whereKey(1)->update(['status' => 'closed']);
        $this->tickets->calls = [];

        $this->app['auth']->forgetGuards();
        $worker = unserialize(serialize($job));
        $worker->setJob(new SyncJob($this->app, '{}', 'sync', 'default'));
        $this->app->call([$worker, 'handle']);

        $this->assertSame([[1, 2]], ReviewTickets::$runs);
        $this->assertSame([], $this->tickets->calls);
    }

    #[Test]
    #[WithConfig('cache.serializable_classes', false)]
    #[WithConfig('rest.authorizations.cache.store', 'database')]
    #[WithConfig('cache.stores.database.connection', null)]
    public function it_reads_and_writes_the_kept_decisions_once_per_chunk(): void
    {
        Rest::resource('cached-reviewed-tickets', CachedReviewedTicketController::class)->register();
        $reads = [];
        $writes = [];
        Event::listen(RetrievingManyKeys::class, function (RetrievingManyKeys $event) use (&$reads): void {
            $reads[] = str_starts_with((string) array_key_first($event->keys), 'rest.authorizations.stamp') ? 'stamps' : count($event->keys);
        });
        Event::listen(WritingManyKeys::class, function (WritingManyKeys $event) use (&$writes): void {
            $writes[] = [$event->seconds ?? 0, count($event->keys)];
        });
        $this->seedRows();
        $this->travel(1)->seconds();

        $this->postJson('/cached-reviewed-tickets/actions/review-tickets', ['search' => []])->assertOk();
        $cold = [$reads, $writes, $this->tickets->calls];
        [$reads, $writes, $this->tickets->calls] = [[], [], []];
        $this->postJson('/cached-reviewed-tickets/actions/review-tickets', ['search' => []])->assertOk();

        $this->assertSame([['stamps', 1, 2, 2, 2, 1], [[300, 1], [300, 2], [300, 2], [300, 2], [300, 1]], self::UPDATES], $cold);
        $this->assertSame([['stamps', 1, 2, 2, 2, 1], [], []], [$reads, $writes, $this->tickets->calls]);
        $this->assertSame([...self::CHUNKS, ...self::CHUNKS], ReviewTickets::$runs);
    }

    #[Test]
    #[DataProvider('misdeclarations')]
    public function it_refuses_an_action_that_cannot_check_its_rows(?string $ability, ActionTarget $target, string $message): void
    {
        ReviewTickets::$ability = $ability;
        ReviewedTicketResource::$target = $target;
        $this->withoutExceptionHandling();

        $this->expectException(InvalidActionException::class);
        $this->expectExceptionMessage($message);

        $this->getJson('/reviewed-tickets?with=actions');
    }

    /** @return array<string, array{?string, ActionTarget, string}> */
    public static function misdeclarations(): array
    {
        return [
            'skipUnauthorized() without an ability' => [null, ActionTarget::Search, ReviewAllowedTickets::class.' calls skipUnauthorized(), but its authorizeEach() returns null: no row is checked, so no row can be skipped.'],
            'an ability without rows' => ['update', ActionTarget::None, ReviewTickets::class.' checks [update] on each row through authorizeEach(), but its target is None: it runs without rows.'],
        ];
    }

    /** @param list<int> $closed */
    private function seedRows(array $closed = []): void
    {
        foreach (range(1, 7) as $id) {
            Ticket::create(['id' => $id, 'team_id' => 1, 'title' => "Ticket {$id}", 'status' => in_array($id, $closed, true) ? 'closed' : 'open']);
        }
    }

    private function ticketSelects(): int
    {
        return count(array_filter(DB::getQueryLog(), fn (array $query): bool => preg_match('/^select .* from ["`]?tickets["`]? /i', $query['query']) === 1));
    }
}

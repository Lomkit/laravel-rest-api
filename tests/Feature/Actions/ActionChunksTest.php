<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ChunkedTicketController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ChunkedTicketResource;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CloseOpenTickets;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\QueuedAtomicCloseOpenTickets;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ActionChunksTest extends HelpdeskTestCase
{
    private const array TITLES = [1 => 'Fan', 2 => 'Badge', 3 => 'Desk', 4 => 'Antenna', 5 => 'Cable', 6 => 'Engine', 7 => 'Gear'];

    private const array OPEN = ['field' => 'status', 'value' => 'open'];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        CloseOpenTickets::reset();
        Rest::resource('chunked-tickets', ChunkedTicketController::class)->register();
        Team::create(['id' => 1, 'name' => 'Support']);

        foreach (self::TITLES as $id => $title) {
            Ticket::create(['id' => $id, 'team_id' => 1, 'title' => $title, 'status' => 'open']);
        }
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<list<int>>  $runs
     */
    #[Test]
    #[DataProvider('searches')]
    public function it_runs_on_every_row_that_the_search_matches_when_the_action_changes_a_filtered_column(array $search, array $runs, int $impacted, int $open): void
    {
        $response = $this->postJson('/chunked-tickets/actions/close-open-tickets', ['search' => $search]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted, 'result' => null]]);
        $this->assertSame($runs, CloseOpenTickets::$runs);
        $this->assertSame($open, Ticket::query()->where('status', 'open')->count());
    }

    /** @return array<string, array{array<string, mixed>, list<list<int>>, int, int}> */
    public static function searches(): array
    {
        return [
            'a filter, in the order of the key' => [['filters' => [self::OPEN]], [[1, 2], [3, 4], [5, 6], [7]], 7, 0],
            'a filter and a sort, in the order of the sort' => [['filters' => [self::OPEN], 'sorts' => [['field' => 'title']]], [[4, 2], [5, 3], [6, 1], [7]], 7, 0],
            'a filter and a limit, in the default order' => [['filters' => [self::OPEN], 'limit' => 10], [[7, 6], [5, 4], [3, 2], [1]], 7, 0],
            'a sort with ties, the ties in descending key order' => [['filters' => [self::OPEN], 'sorts' => [['field' => 'status']]], [[7, 6], [5, 4], [3, 2], [1]], 7, 0],
        ];
    }

    #[Test]
    public function it_runs_an_action_without_search_on_every_row_in_chunks_of_its_size(): void
    {
        Ticket::query()->insert(array_map(fn (int $id): array => ['id' => $id, 'team_id' => 1, 'title' => "Ticket {$id}", 'status' => 'open'], range(8, 250)));

        $response = $this->postJson('/chunked-tickets/actions/close-open-tickets');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 250, 'result' => null]]);
        $this->assertSame(array_chunk(range(1, 250), 2), CloseOpenTickets::$runs);
        $this->assertSame(0, Ticket::query()->where('status', 'open')->count());
    }

    #[Test]
    public function it_runs_on_the_named_rows_in_the_order_of_resources(): void
    {
        ChunkedTicketResource::$target = ActionTarget::Resources;

        $response = $this->postJson('/chunked-tickets/actions/close-open-tickets', ['resources' => [5, 2, 7]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertSame([[5, 2], [7]], CloseOpenTickets::$runs);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<list<int>>  $runs
     */
    #[Test]
    #[DataProvider('growingSearches')]
    public function it_runs_on_the_keys_that_the_row_checks_read_when_the_search_is_sorted_or_limited(array $search, array $runs, int $impacted): void
    {
        $this->app->instance(TicketPolicy::class, $policy = new class extends TicketPolicy
        {
            #[\Override]
            public function update(?Authenticatable $user, Model $model): Response
            {
                if ($model->getKey() === 7 && Ticket::query()->whereKey(8)->doesntExist()) {
                    Ticket::create(['id' => 8, 'team_id' => 1, 'title' => 'Hub', 'status' => 'open']);
                }

                return parent::update($user, $model);
            }
        });

        $response = $this->postJson('/chunked-tickets/actions/checked-close-open-tickets', ['search' => $search]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted, 'result' => null]]);
        $this->assertSame($runs, CloseOpenTickets::$runs);
        $this->assertNotContains('update 8', $policy->calls);
    }

    /** @return array<string, array{array<string, mixed>, list<list<int>>, int}> */
    public static function growingSearches(): array
    {
        return [
            'a sorted search reads its keys once' => [['filters' => [self::OPEN], 'sorts' => [['field' => 'title']]], [[4, 2], [5, 3], [6, 1], [7]], 7],
            'a search without sorts reads its rows again' => [['filters' => [self::OPEN]], [[1, 2], [3, 4], [5, 6], [7, 8]], 8],
        ];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('cappedSearches')]
    public function it_refuses_a_search_over_more_rows_than_max_resources(array $body): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/chunked-tickets/actions/capped-close-open-tickets', $body);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'This action can be run on at most 5 resources; narrow the search.',
            'errors' => ['search' => ['This action can be run on at most 5 resources; narrow the search.']],
        ]);
        $this->assertCount(1, $this->ticketQueries());
        $this->assertSame([], CloseOpenTickets::$runs);
        $this->assertSame(7, Ticket::query()->where('status', 'open')->count());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function cappedSearches(): array
    {
        return [
            'every row' => [[]],
            'a sort' => [['search' => ['sorts' => [['field' => 'title']]]]],
            'a limit above the cap' => [['search' => ['limit' => 10]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<list<int>>  $runs
     */
    #[Test]
    #[DataProvider('searchesAtTheCap')]
    public function it_runs_a_search_up_to_max_resources(array $search, array $runs): void
    {
        Ticket::query()->whereKey([1, 2])->update(['status' => 'closed']);

        $response = $this->postJson('/chunked-tickets/actions/capped-close-open-tickets', ['search' => $search]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 5, 'result' => null]]);
        $this->assertSame($runs, CloseOpenTickets::$runs);
    }

    /** @return array<string, array{array<string, mixed>, list<list<int>>}> */
    public static function searchesAtTheCap(): array
    {
        return [
            'a filter' => [['filters' => [self::OPEN]], [[3, 4], [5, 6], [7]]],
            'a filter and a sort' => [['filters' => [self::OPEN], 'sorts' => [['field' => 'title']]], [[4, 5], [3, 6], [7]]],
        ];
    }

    #[Test]
    public function it_refuses_more_named_rows_than_max_resources(): void
    {
        ChunkedTicketResource::$target = ActionTarget::Resources;

        $response = $this->postJson('/chunked-tickets/actions/capped-close-open-tickets', ['resources' => [7, 6, 5, 4, 3, 2]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The resources field must not have more than 5 items.',
            'errors' => ['resources' => ['The resources field must not have more than 5 items.']],
        ]);
        $this->assertSame([], CloseOpenTickets::$runs);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<list<int>>  $runs
     * @param  list<string>  $queries
     */
    #[Test]
    #[DataProvider('uncappedReadings')]
    public function it_runs_an_action_without_a_cap_on_more_rows_than_max_resources_holds(ActionTarget $target, array $body, array $runs, array $queries): void
    {
        ChunkedTicketResource::$target = $target;
        DB::enableQueryLog();

        $response = $this->postJson('/chunked-tickets/actions/uncapped-close-open-tickets', $body);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 7, 'result' => null]]);
        $this->assertSame($runs, CloseOpenTickets::$runs);
        $this->assertSame($queries, $this->ticketQueries());
    }

    /** @return array<string, array{ActionTarget, array<string, mixed>, list<list<int>>, list<string>}> */
    public static function uncappedReadings(): array
    {
        return [
            'a search without sorts: chunks after the last key, without a count' => [ActionTarget::Search, ['search' => ['filters' => [self::OPEN]]], [[1, 2], [3, 4], [5, 6], [7]], [
                'select * from tickets where (tickets.status = ?) and tickets.id is not null order by tickets.id asc limit 2',
                'select * from tickets where (tickets.status = ?) and tickets.id > ? order by tickets.id asc limit 2',
                'select * from tickets where (tickets.status = ?) and tickets.id > ? order by tickets.id asc limit 2',
                'select * from tickets where (tickets.status = ?) and tickets.id > ? order by tickets.id asc limit 2',
            ]],
            'a sorted search: every key, then chunks of keys' => [ActionTarget::Search, ['search' => ['filters' => [self::OPEN], 'sorts' => [['field' => 'title']]]], [[4, 2], [5, 3], [6, 1], [7]], [
                'select tickets.id from tickets where (tickets.status = ?) order by tickets.title asc, tickets.id desc',
                'select * from tickets where (tickets.status = ?) and tickets.id in (4, 2)',
                'select * from tickets where (tickets.status = ?) and tickets.id in (5, 3)',
                'select * from tickets where (tickets.status = ?) and tickets.id in (6, 1)',
                'select * from tickets where (tickets.status = ?) and tickets.id in (7)',
            ]],
            'named rows: the key check, then chunks of keys' => [ActionTarget::Resources, ['resources' => [7, 6, 5, 4, 3, 2, 1]], [[7, 6], [5, 4], [3, 2], [1]], [
                'select tickets.id from tickets where tickets.id in (7, 6, 5, 4, 3, 2, 1)',
                'select * from tickets where tickets.id in (7, 6)',
                'select * from tickets where tickets.id in (5, 4)',
                'select * from tickets where tickets.id in (3, 2)',
                'select * from tickets where tickets.id in (1)',
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<string>  $queries
     */
    #[Test]
    #[DataProvider('readings')]
    public function it_reads_the_rows_of_each_target_by_key(ActionTarget $target, array $body, array $queries): void
    {
        ChunkedTicketResource::$target = $target;
        DB::enableQueryLog();

        $this->postJson('/chunked-tickets/actions/close-open-tickets', $body)->assertOk();

        $this->assertSame($queries, $this->ticketQueries());
    }

    /** @return array<string, array{ActionTarget, array<string, mixed>, list<string>}> */
    public static function readings(): array
    {
        return [
            'a search without sorts: a count, then chunks after the last key' => [ActionTarget::Search, ['search' => ['filters' => [self::OPEN]]], [
                'select count(*) as aggregate from tickets where (tickets.status = ?)',
                'select * from tickets where (tickets.status = ?) and tickets.id is not null order by tickets.id asc limit 2',
                'select * from tickets where (tickets.status = ?) and tickets.id > ? order by tickets.id asc limit 2',
                'select * from tickets where (tickets.status = ?) and tickets.id > ? order by tickets.id asc limit 2',
                'select * from tickets where (tickets.status = ?) and tickets.id > ? order by tickets.id asc limit 2',
            ]],
            'a sorted search: the keys, then chunks of keys' => [ActionTarget::Search, ['search' => ['filters' => [self::OPEN], 'sorts' => [['field' => 'title']]]], [
                'select tickets.id from tickets where (tickets.status = ?) order by tickets.title asc, tickets.id desc limit 1001',
                'select * from tickets where (tickets.status = ?) and tickets.id in (4, 2)',
                'select * from tickets where (tickets.status = ?) and tickets.id in (5, 3)',
                'select * from tickets where (tickets.status = ?) and tickets.id in (6, 1)',
                'select * from tickets where (tickets.status = ?) and tickets.id in (7)',
            ]],
            'named rows: the key check, then chunks of keys' => [ActionTarget::Resources, ['resources' => [5, 2, 7]], [
                'select tickets.id from tickets where tickets.id in (5, 2, 7)',
                'select * from tickets where tickets.id in (5, 2)',
                'select * from tickets where tickets.id in (7)',
            ]],
        ];
    }

    #[Test]
    public function it_refuses_a_queued_action_with_one_transaction_for_the_whole_run(): void
    {
        ChunkedTicketResource::$queuedAtomic = true;
        $this->withoutExceptionHandling();

        $this->expectException(InvalidActionException::class);
        $this->expectExceptionMessage(QueuedAtomicCloseOpenTickets::class.' runs its chunks in one transaction (ActionTransaction::Whole), but it implements ShouldQueue: its chunks run in the worker, outside the request.');

        $this->getJson('/chunked-tickets?with=actions');
    }

    /** @return list<string> */
    private function ticketQueries(): array
    {
        return collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => preg_match('/^select .* from ["`]?tickets["`]?( |$)/', $query) === 1)
            ->map(fn (string $query): string => str_replace(['"', '`'], '', $query))
            ->values()
            ->all();
    }
}

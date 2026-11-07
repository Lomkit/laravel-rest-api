<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ChunkedTicketController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ChunkedTicketResource;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CloseOpenTickets;
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
}

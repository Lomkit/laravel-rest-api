<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Search\Stubs\KeyOrderedChannelController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\StatusOrderedTeamController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\StatusOrderedTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class KeyTiebreakTest extends PackageTestCase
{
    use CapturesQueries;

    private const array STATUSES = ['open', 'closed', 'pending'];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Team::query()->create(['id' => 1, 'name' => 'Billing']);
        Team::query()->create(['id' => 2, 'name' => 'Support']);
        Rest::resource('status-ordered-tickets', StatusOrderedTicketController::class)->register();
        Rest::resource('status-ordered-teams', StatusOrderedTeamController::class)->register();
    }

    #[Test]
    public function it_returns_every_row_once_across_the_pages_of_a_sort_with_ties(): void
    {
        $this->seedTickets(range(90, 1, -1));
        DB::table('tickets')->where('id', '<=', 22)->update(['title' => 'Updated']);
        $seen = [];

        foreach (range(1, 9) as $page) {
            $response = $this->postJson('/api/tickets/search', ['search' => [
                'sorts' => [['field' => 'status']],
                'selects' => [['field' => 'id']],
                'limit' => 10,
                'page' => $page,
            ]]);

            $response->assertOk();
            array_push($seen, ...$response->json('data.*.id'));
        }

        $this->assertSame([...self::ids(1, 90), ...self::ids(0, 90), ...self::ids(2, 90)], $seen);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('orders')]
    public function it_ends_the_order_of_a_search_with_the_key(string $endpoint, array $search, string $sql, array $ids): void
    {
        $this->seedTickets([1, 3, 5, 2, 4, 6]);
        $this->captureQueries();

        $response = $this->postJson($endpoint, ['search' => [...$search, 'selects' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $ids);
        $this->assertSame([$sql], array_column($this->readsOf('tickets'), 0));
    }

    /** @return array<string, array{string, array<string, mixed>, string, list<int>}> */
    public static function orders(): array
    {
        return [
            'a sort' => ['/api/tickets/search', ['sorts' => [['field' => 'status']]], 'select tickets.id, tickets.status from tickets order by tickets.status asc, tickets.id desc limit 50 offset 0', [4, 1, 6, 3, 5, 2]],
            'a sort in descending order' => ['/api/tickets/search', ['sorts' => [['field' => 'team_id', 'direction' => 'desc']]], 'select tickets.id, tickets.status from tickets order by tickets.team_id desc, tickets.id desc limit 50 offset 0', [6, 4, 2, 5, 3, 1]],
            'a default order without the key' => ['/status-ordered-tickets/search', [], 'select tickets.id, tickets.status from tickets order by tickets.status asc, tickets.id desc limit 50 offset 0', [4, 1, 6, 3, 5, 2]],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('keyOrders')]
    public function it_orders_by_the_key_once_when_the_order_already_holds_it(string $endpoint, array $search, string $table, string $sql): void
    {
        $this->seedTickets([1, 2]);
        Channel::query()->create(['code' => 'email', 'name' => 'Email']);
        Rest::resource('key-ordered-channels', KeyOrderedChannelController::class)->register();
        $this->captureQueries();

        $response = $this->postJson($endpoint, ['search' => $search]);

        $response->assertOk();
        $this->assertSame([$sql], array_column($this->readsOf($table), 0));
    }

    /** @return array<string, array{string, array<string, mixed>, string, string}> */
    public static function keyOrders(): array
    {
        return [
            'a sort on the key' => ['/api/tickets/search', ['sorts' => [['field' => 'id']], 'selects' => [['field' => 'id']]], 'tickets', 'select tickets.id, tickets.status from tickets order by tickets.id asc limit 50 offset 0'],
            'the default order' => ['/api/tickets/search', ['selects' => [['field' => 'id']]], 'tickets', 'select tickets.id, tickets.status from tickets order by tickets.id desc limit 50 offset 0'],
            'a default order on a string key' => ['/key-ordered-channels/search', ['selects' => [['field' => 'code']]], 'channels', 'select channels.code from channels order by channels.code desc limit 50 offset 0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $include
     * @param  list<int>  $tickets
     */
    #[Test]
    #[DataProvider('includes')]
    public function it_ends_the_order_of_an_include_with_the_key(array $include, string $sql, array $tickets): void
    {
        $this->seedTickets([1, 3, 5, 7, 9, 11, 2, 4, 6, 8, 10, 12]);
        $this->captureQueries();

        $response = $this->postJson('/status-ordered-teams/search', ['search' => [
            'includes' => [$include],
            'sorts' => [['field' => 'id', 'direction' => 'asc']],
            'selects' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.tickets.*.id', $tickets);
        $this->assertSame($sql, array_column($this->readsOf('tickets'), 0)[0]);
    }

    /** @return array<string, array{array<string, mixed>, string, list<int>}> */
    public static function includes(): array
    {
        $selected = 'select * from (select tickets.id, tickets.status, tickets.team_id, row_number() over (partition by tickets.team_id order by tickets.status asc, tickets.id desc) as laravel_row from tickets where tickets.team_id in (1, 2)) as laravel_table where laravel_row <= 3 order by laravel_row';
        $every = 'select * from (select tickets.id, tickets.team_id, tickets.title, tickets.status, row_number() over (partition by tickets.team_id order by tickets.status asc, tickets.id desc) as laravel_row from tickets where tickets.team_id in (1, 2)) as laravel_table where laravel_row <= 3 order by laravel_row';

        return [
            'an include with a sort' => [['relation' => 'tickets', 'sorts' => [['field' => 'status']], 'selects' => [['field' => 'id']]], $selected, [7, 1, 10, 4]],
            'an include with a default order without the key' => [['relation' => 'tickets', 'selects' => [['field' => 'id']]], $selected, [7, 1, 10, 4]],
            'the implicit level of a dotted include' => [['relation' => 'tickets.team'], $every, [7, 1, 10, 4]],
        ];
    }

    /** @param list<int> $ids */
    private function seedTickets(array $ids): void
    {
        DB::table('tickets')->insert(array_map(
            static fn (int $id): array => ['id' => $id, 'team_id' => 2 - $id % 2, 'title' => "Ticket {$id}", 'status' => self::STATUSES[$id % 3]],
            $ids,
        ));
    }

    /** @return list<int> */
    private static function ids(int $remainder, int $count): array
    {
        return array_values(array_filter(range($count, 1, -1), static fn (int $id): bool => $id % 3 === $remainder));
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Reply;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class IncludeLimitRelationsTest extends PackageTestCase
{
    private const array ID = [['field' => 'id']];

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

        foreach (range(1, 24) as $ticket) {
            Ticket::query()->create(['id' => $ticket, 'team_id' => $ticket <= 12 ? 1 : 2, 'title' => 'Ticket '.$ticket, 'status' => 'open']);
            Reply::query()->create(['id' => $ticket, 'ticket_id' => $ticket, 'body' => 'Reply '.$ticket]);
        }

        foreach (range(1, 12) as $tag) {
            Tag::query()->create(['id' => $tag, 'name' => 'Tag '.$tag]);

            foreach ([1, 13, 24] as $ticket) {
                DB::table('tag_ticket')->insert(['tag_id' => $tag, 'ticket_id' => $ticket, 'position' => $tag]);
                DB::table('labelables')->insert(['tag_id' => $tag, 'labelable_id' => $ticket, 'labelable_type' => Ticket::class, 'position' => $tag]);
            }
        }
    }

    /** @param list<list<int>> $ids */
    #[Test]
    #[DataProvider('teamIncludes')]
    public function it_loads_the_default_number_of_rows_for_each_team(string $relation, array $ids): void
    {
        $response = $this->postJson('/api/teams/search', ['search' => ['selects' => self::ID, 'includes' => [['relation' => $relation, 'selects' => self::ID]]]]);

        $response->assertOk();
        $this->assertSame($ids, array_map(fn (array $team): array => array_column($team[$relation], 'id'), $response->json('data')));
    }

    /** @return array<string, array{string, list<list<int>>}> */
    public static function teamIncludes(): array
    {
        return [
            'a has many' => ['tickets', [range(24, 15), range(12, 3)]],
            'a has many through' => ['replies', [range(24, 15), range(12, 3)]],
        ];
    }

    #[Test]
    #[DataProvider('pivotIncludes')]
    public function it_loads_the_default_number_of_rows_with_their_pivot_for_each_ticket(string $relation): void
    {
        $response = $this->postJson('/api/tickets/search', ['search' => [
            'filters' => [['field' => 'id', 'operator' => 'in', 'value' => [1, 13]]],
            'selects' => self::ID,
            'includes' => [['relation' => $relation, 'selects' => self::ID]],
        ]]);

        $rows = array_map(fn (int $tag): array => ['id' => $tag, 'pivot' => ['position' => $tag]], range(12, 3));
        $response->assertOk()->assertJsonPath('data', [['id' => 13, $relation => $rows, '@more' => [$relation]], ['id' => 1, $relation => $rows, '@more' => [$relation]]]);
    }

    /** @return array<string, array{string}> */
    public static function pivotIncludes(): array
    {
        return [
            'a belongs to many' => ['tags'],
            'a morph to many' => ['labels'],
        ];
    }

    #[Test]
    public function it_limits_each_level_of_a_dotted_include(): void
    {
        $response = $this->postJson('/api/teams/search', ['search' => ['selects' => self::ID, 'includes' => [['relation' => 'tickets.tags', 'selects' => self::ID]]]]);

        $response->assertOk();
        $this->assertSame([range(24, 15), range(12, 3)], array_map(fn (array $team): array => array_column($team['tickets'], 'id'), $response->json('data')));
        $this->assertSame([range(12, 3), []], array_map(fn (array $team): array => array_column($team['tickets'][0]['tags'], 'id'), $response->json('data')));
    }
}

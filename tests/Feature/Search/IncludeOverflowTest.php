<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ObservedTeamController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ObservedTeamResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ObservedTeamResponse;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class IncludeOverflowTest extends PackageTestCase
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

        DB::table('teams')->insert([['id' => 1, 'name' => 'Billing'], ['id' => 2, 'name' => 'Support'], ['id' => 3, 'name' => 'Network']]);

        $tickets = [];
        $replies = [];

        foreach (range(1, 213) as $id) {
            $team = match (true) {
                $id <= 200 => 1,
                $id <= 203 => 2,
                default => 3,
            };
            $tickets[] = ['id' => $id, 'team_id' => $team, 'title' => 'Ticket '.$id, 'status' => $id <= 5 ? 'closed' : 'open'];
            $replies[] = ['id' => $id, 'ticket_id' => $id, 'body' => 'Reply '.$id];
        }

        DB::table('tickets')->insert($tickets);
        DB::table('replies')->insert($replies);
        DB::table('tags')->insert(array_map(fn (int $tag): array => ['id' => $tag, 'name' => 'Tag '.$tag], range(1, 11)));
        DB::table('tag_ticket')->insert([
            ...array_map(fn (int $tag): array => ['tag_id' => $tag, 'ticket_id' => 213], range(1, 11)),
            ['tag_id' => 1, 'ticket_id' => 212],
            ['tag_id' => 2, 'ticket_id' => 212],
        ]);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<array{int, int, list<string>}>  $rows
     */
    #[Test]
    #[DataProvider('limitedIncludes')]
    public function it_lists_the_includes_of_each_row_that_have_more_rows(string $uri, array $search, string $key, array $rows): void
    {
        $response = $this->postJson($uri, ['search' => ['selects' => self::ID, ...$search]]);

        $response->assertOk();
        $this->assertSame($rows, self::summary($response, $key));
    }

    /** @return array<string, array{string, array<string, mixed>, string, list<array{int, int, list<string>}>}> */
    public static function limitedIncludes(): array
    {
        return [
            'a has many with the default limit' => ['/api/teams/search', ['includes' => [['relation' => 'tickets', 'selects' => self::ID]]], 'tickets', [[3, 10, []], [2, 3, []], [1, 10, ['tickets']]]],
            'a has many with a limit' => ['/api/teams/search', ['includes' => [['relation' => 'tickets', 'limit' => 25, 'selects' => self::ID]]], 'tickets', [[3, 10, []], [2, 3, []], [1, 25, ['tickets']]]],
            'a has many through' => ['/api/teams/search', ['includes' => [['relation' => 'replies', 'selects' => self::ID]]], 'replies', [[3, 10, []], [2, 3, []], [1, 10, ['replies']]]],
            'a belongs to many' => ['/api/tickets/search', ['filters' => [['field' => 'id', 'operator' => 'in', 'value' => [212, 213]]], 'includes' => [['relation' => 'tags', 'selects' => self::ID]]], 'tags', [[213, 10, ['tags']], [212, 2, []]]],
        ];
    }

    #[Test]
    public function it_answers_a_row_with_its_more_key_after_its_includes(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/tickets/search', ['search' => [
            'selects' => self::ID,
            'filters' => [['field' => 'id', 'operator' => 'in', 'value' => [212, 213]]],
            'includes' => [['relation' => 'tags', 'selects' => self::ID]],
        ]]);

        $tags = fn (int ...$ids): array => array_map(fn (int $id): array => ['id' => $id, 'pivot' => ['position' => null]], $ids);
        $response->assertOk()->assertJsonPath('data', [
            ['id' => 213, 'tags' => $tags(...range(11, 2)), '@more' => ['tags']],
            ['id' => 212, 'tags' => $tags(2, 1), '@more' => []],
        ]);
        $this->assertCount(2, DB::getQueryLog());
    }

    #[Test]
    public function it_gives_each_alias_of_a_relation_its_own_signal(): void
    {
        $response = $this->postJson('/api/teams/search', ['search' => [
            'selects' => self::ID,
            'filters' => [['field' => 'id', 'value' => 1]],
            'includes' => [
                ['relation' => 'tickets', 'alias' => 'closed', 'filters' => [['field' => 'status', 'value' => 'closed']], 'selects' => self::ID],
                ['relation' => 'tickets', 'alias' => 'open', 'filters' => [['field' => 'status', 'value' => 'open']], 'selects' => self::ID],
            ],
        ]]);

        $response->assertOk()
            ->assertJsonPath('data.0.closed', array_map(fn (int $id): array => ['id' => $id], range(5, 1)))
            ->assertJsonPath('data.0.open', array_map(fn (int $id): array => ['id' => $id], range(200, 191)))
            ->assertJsonPath('data.0.@more', ['open']);
    }

    /** @param array<string, mixed> $include */
    #[Test]
    #[DataProvider('nestedIncludes')]
    public function it_lists_the_includes_with_more_rows_on_the_rows_of_each_level(array $include): void
    {
        $response = $this->postJson('/api/teams/search', ['search' => ['selects' => self::ID, 'filters' => [['field' => 'id', 'value' => 3]], 'includes' => [$include]]]);

        $tickets = $response->assertOk()->json('data.0.tickets');
        $this->assertIsArray($tickets);
        $this->assertSame([], $response->json('data.0.@more'));
        $this->assertSame(
            [[213, 10, ['tags']], [212, 2, []], ...array_map(fn (int $id): array => [$id, 0, []], range(211, 204))],
            array_map(fn (array $ticket): array => [$ticket['id'], count($ticket['tags']), $ticket['@more']], $tickets),
        );
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function nestedIncludes(): array
    {
        return [
            'a nested include' => [['relation' => 'tickets', 'selects' => self::ID, 'includes' => [['relation' => 'tags', 'selects' => self::ID]]]],
            'the middle level of a dotted include' => [['relation' => 'tickets.tags', 'selects' => self::ID]],
        ];
    }

    #[Test]
    public function it_leaves_out_the_more_key_on_rows_without_a_limited_include(): void
    {
        $response = $this->postJson('/api/tickets/search', ['search' => [
            'selects' => self::ID,
            'filters' => [['field' => 'id', 'value' => 213]],
            'includes' => [['relation' => 'team', 'selects' => self::ID]],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 213, 'team' => ['id' => 3]]]);
    }

    #[Test]
    public function it_hands_only_the_kept_rows_to_the_searched_event_map_and_the_gates(): void
    {
        Rest::resource('observed-teams', ObservedTeamController::class)->register();
        ObservedTeamResource::$searched = [];
        ObservedTeamResponse::$mapped = [];
        $this->app->instance(TicketPolicy::class, $policy = new TicketPolicy);

        $response = $this->postJson('/observed-teams/search', ['search' => [
            'selects' => self::ID,
            'includes' => [['relation' => 'tickets', 'selects' => self::ID, 'gates' => ['update']]],
        ]]);

        $response->assertOk();
        $this->assertSame([3 => 10, 2 => 3, 1 => 10], ObservedTeamResource::$searched);
        $this->assertSame([3 => 10, 2 => 3, 1 => 10], ObservedTeamResponse::$mapped);
        $updates = array_values(array_filter($policy->calls, fn (string $call): bool => str_starts_with($call, 'update ')));
        sort($updates, SORT_NATURAL);
        $this->assertSame(array_map(fn (int $id): string => 'update '.$id, range(191, 213)), $updates);
        $response->assertJsonPath('data.*.@more', [[], [], ['tickets']]);
    }

    /** @return list<array{int, int, list<string>}> */
    private static function summary(TestResponse $response, string $key): array
    {
        $rows = $response->json('data');
        self::assertIsArray($rows);

        return array_map(fn (array $row): array => [$row['id'], count($row[$key]), $row['@more']], $rows);
    }
}

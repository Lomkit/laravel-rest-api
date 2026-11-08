<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ResponseRowsTest extends PackageTestCase
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

        DB::table('teams')->insert([['id' => 1, 'name' => 'Billing'], ['id' => 2, 'name' => 'Support']]);
        DB::table('tickets')->insert([
            ['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open'],
            ['id' => 2, 'team_id' => 1, 'title' => 'Password reset', 'status' => 'open'],
            ['id' => 3, 'team_id' => 2, 'title' => 'Laptop setup', 'status' => 'open'],
        ]);
        DB::table('tags')->insert([['id' => 1, 'name' => 'Hardware'], ['id' => 2, 'name' => 'Access']]);
        DB::table('tag_ticket')->insert([['tag_id' => 1, 'ticket_id' => 1], ['tag_id' => 2, 'ticket_id' => 1], ['tag_id' => 2, 'ticket_id' => 2]]);
    }

    #[Test]
    public function it_refuses_a_search_over_ten_thousand_rows_by_default_before_any_query(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/teams/search', ['search' => ['includes' => [
            ['relation' => 'tickets', 'includes' => [['relation' => 'tags'], ['relation' => 'labels']]],
        ]]]);

        $this->assertRefused($response, 'search.includes.0.includes.1.relation', 10000, 10550);
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_runs_a_search_at_the_limit(): void
    {
        config(['rest.limits.sizes.rows' => 1110]);

        $response = $this->postJson('/api/teams/search', ['search' => ['limit' => 10, 'selects' => self::ID, 'includes' => [
            ['relation' => 'tickets', 'selects' => self::ID, 'includes' => [['relation' => 'tags', 'selects' => self::ID]]],
        ]]]);

        $response->assertOk();
        $this->assertSame([[2, [3], []], [1, [2, 1], [2]]], array_map(
            fn (array $team): array => [$team['id'], array_column($team['tickets'], 'id'), array_column($team['tickets'][0]['tags'], 'id')],
            $response->json('data'),
        ));
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searchesOverTheLimit')]
    public function it_names_the_key_that_takes_the_search_over_the_limit(string $uri, int $max, array $search, string $key, int $rows): void
    {
        config(['rest.limits.sizes.rows' => $max]);

        $response = $this->postJson($uri, ['search' => $search]);

        $this->assertRefused($response, $key, $max, $rows);
    }

    /** @return array<string, array{string, int, array<string, mixed>, string, int}> */
    public static function searchesOverTheLimit(): array
    {
        $teams = '/api/teams/search';

        return [
            'the default page limit' => [$teams, 49, [], 'search', 50],
            'the page limit' => [$teams, 24, ['limit' => 25], 'search.limit', 25],
            'the default limit of an include' => [$teams, 109, ['limit' => 10, 'includes' => [['relation' => 'tickets']]], 'search.includes.0.relation', 110],
            'the limit of an include' => [$teams, 259, ['limit' => 10, 'includes' => [['relation' => 'tickets', 'limit' => 25]]], 'search.includes.0.limit', 260],
            'nested includes' => [$teams, 1109, ['limit' => 10, 'includes' => [['relation' => 'tickets', 'includes' => [['relation' => 'tags']]]]], 'search.includes.0.includes.0.relation', 1110],
            'sibling includes' => [$teams, 209, ['limit' => 10, 'includes' => [['relation' => 'tickets'], ['relation' => 'replies']]], 'search.includes.1.relation', 210],
            'an aliased include' => [$teams, 209, ['limit' => 10, 'includes' => [['relation' => 'tickets'], ['relation' => 'tickets', 'alias' => 'open_tickets']]], 'search.includes.1.relation', 210],
            'a to-one include' => ['/api/tickets/search', 19, ['limit' => 10, 'includes' => [['relation' => 'team']]], 'search.includes.0.relation', 20],
            'the middle level of a dotted include' => [$teams, 109, ['limit' => 10, 'includes' => [['relation' => 'tickets.tags', 'limit' => 25]]], 'search.includes.0.relation', 2610],
            'the last level of a dotted include' => [$teams, 2609, ['limit' => 10, 'includes' => [['relation' => 'tickets.tags', 'limit' => 25]]], 'search.includes.0.limit', 2610],
            'a relation that a dotted include shares' => [$teams, 1109, ['limit' => 10, 'includes' => [['relation' => 'tickets'], ['relation' => 'tickets.tags']]], 'search.includes.1.relation', 1110],
        ];
    }

    #[Test]
    public function it_counts_once_the_relation_that_a_dotted_include_shares(): void
    {
        config(['rest.limits.sizes.rows' => 1110]);

        $response = $this->postJson('/api/teams/search', ['search' => ['limit' => 10, 'selects' => self::ID, 'includes' => [
            ['relation' => 'tickets', 'selects' => self::ID],
            ['relation' => 'tickets.tags', 'selects' => self::ID],
        ]]]);

        $response->assertOk();
        $this->assertSame([2, 1], array_column($response->json('data.1.tickets.1.tags'), 'id'));
    }

    #[Test]
    public function it_bounds_the_records_of_a_mutation_response(): void
    {
        config(['rest.limits.sizes.rows' => 21]);

        $response = $this->postJson('/api/teams/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['name' => 'Accounts']], ['operation' => 'update', 'key' => 2, 'attributes' => ['name' => 'Desk']]],
            'response' => ['selects' => self::ID, 'includes' => [['relation' => 'tickets', 'selects' => self::ID]]],
        ]);

        $this->assertRefused($response, 'response.includes.0.relation', 21, 22);
        $this->assertSame(['Billing', 'Support'], DB::table('teams')->orderBy('id')->pluck('name')->all());
    }

    #[Test]
    public function it_names_the_response_when_its_records_alone_go_over_the_limit(): void
    {
        config(['rest.limits.sizes.rows' => 1]);

        $response = $this->postJson('/api/teams/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['name' => 'Accounts']], ['operation' => 'update', 'key' => 2, 'attributes' => ['name' => 'Desk']]],
            'response' => ['selects' => self::ID],
        ]);

        $this->assertRefused($response, 'response', 1, 2);
    }

    #[Test]
    public function it_answers_the_records_of_a_mutation_at_the_limit(): void
    {
        config(['rest.limits.sizes.rows' => 22]);

        $response = $this->postJson('/api/teams/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['name' => 'Accounts']], ['operation' => 'update', 'key' => 2, 'attributes' => ['name' => 'Desk']]],
            'response' => ['selects' => self::ID, 'includes' => [['relation' => 'tickets', 'selects' => self::ID]]],
        ]);

        $response->assertOk();
        $this->assertSame([[2, 1], [3]], array_map(fn (array $team): array => array_column($team['tickets'], 'id'), $response->json('records')));
    }

    #[Test]
    public function it_leaves_the_search_of_an_action_out(): void
    {
        config(['rest.limits.sizes.rows' => 1]);

        $this->postJson('/api/tickets/actions/close-tickets', ['search' => ['includes' => [['relation' => 'tags']]]])
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
    }

    #[Test]
    public function it_bounds_a_page_read_outside_http(): void
    {
        config(['rest.limits.sizes.rows' => 49]);
        $message = 'The search field takes the response over the limit of 49 rows: it can render up to 50 rows.';

        try {
            Rest::search(TeamResource::class)
                ->as(null)
                ->page();
            $this->fail('The page was read.');
        } catch (ValidationException $exception) {
            $this->assertSame(['search' => [$message]], $exception->errors());
        }
    }

    #[Test]
    public function it_leaves_a_lazy_read_out(): void
    {
        config(['rest.limits.sizes.rows' => 1]);

        $rows = Rest::search(TeamResource::class)
            ->as(null)
            ->payload(['selects' => self::ID, 'includes' => [['relation' => 'tickets', 'selects' => self::ID]]])
            ->lazy(2);

        $this->assertSame([[2, [3]], [1, [2, 1]]], $rows->map(fn (array $team): array => [$team['id'], array_column($team['tickets'], 'id')])->all());
    }

    private function assertRefused(TestResponse $response, string $key, int $max, int $rows): void
    {
        $message = "The {$key} field takes the response over the limit of {$max} rows: it can render up to {$rows} rows.";

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }
}

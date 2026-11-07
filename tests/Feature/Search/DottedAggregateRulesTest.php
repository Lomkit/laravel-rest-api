<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DottedAggregateRulesTest extends PackageTestCase
{
    use SeedsOpenTickets;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedOpenTickets();
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('deepPaths')]
    #[WithConfig('rest.limits.depths.includes', 2)]
    public function it_refuses_a_path_longer_than_the_include_depth(array $search, string $key): void
    {
        $message = "The {$key} field goes deeper than the limit of 2 include levels.";

        $response = $this->postJson('/open-teams/search', ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function deepPaths(): array
    {
        return [
            'at the root' => [['aggregates' => [['relation' => 'tickets.tags.labelledTickets', 'type' => 'count']]], 'search.aggregates.0.relation'],
            'in an include' => [['includes' => [['relation' => 'tickets', 'aggregates' => [['relation' => 'tags.labelledTickets.team', 'type' => 'count']]]]], 'search.includes.0.aggregates.0.relation'],
        ];
    }

    #[Test]
    #[WithConfig('rest.limits.depths.includes', 2)]
    public function it_counts_the_depth_of_a_path_from_its_own_level(): void
    {
        $response = $this->postJson('/open-teams/search', ['search' => [
            'selects' => [['field' => 'id']],
            'filters' => [['field' => 'id', 'value' => 2]],
            'includes' => [['relation' => 'tickets', 'selects' => [['field' => 'id']], 'aggregates' => [['relation' => 'tags.labelledTickets', 'type' => 'count']]]],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 2, 'tickets' => [['id' => 4, 'tags_labelled_tickets_count' => 3]], '@more' => []]]);
    }

    #[Test]
    #[WithConfig('rest.limits.depths.includes', 0)]
    public function it_aggregates_one_relation_whatever_the_include_depth(): void
    {
        $response = $this->search('/open-teams/search', [['relation' => 'tickets', 'type' => 'count']]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, 'tickets_count' => 2], ['id' => 2, 'tickets_count' => 1], ['id' => 3, 'tickets_count' => 1]]);
    }

    #[Test]
    #[WithConfig('rest.limits.depths.includes', 0)]
    public function it_refuses_a_path_when_the_include_depth_is_zero(): void
    {
        $message = 'The search.aggregates.0.relation field goes deeper than the limit of 0 include levels.';

        $response = $this->search('/open-teams/search', [['relation' => 'tickets.tags', 'type' => 'count']]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.aggregates.0.relation' => [$message]]]);
    }

    /** @param list<int> $counts */
    #[Test]
    #[DataProvider('leafFilters')]
    public function it_filters_the_rows_at_the_end_of_a_path(string $value, array $counts): void
    {
        $response = $this->search('/open-teams/search', [['relation' => 'tickets.tags', 'type' => 'count', 'filters' => [['field' => 'name', 'value' => $value]]]]);

        $response->assertOk();
        $this->assertSame($counts, array_column($response->json('data'), 'tickets_tags_count'));
    }

    /** @return array<string, array{string, list<int>}> */
    public static function leafFilters(): array
    {
        return [
            'a related row of a visible row' => ['Hardware', [1, 0, 0]],
            'a related row of a visible row and of a hidden row' => ['Network', [0, 1, 0]],
        ];
    }

    #[Test]
    public function it_refuses_a_filter_on_a_field_of_a_middle_relation(): void
    {
        $message = 'The selected search.aggregates.0.filters.0.field is invalid.';

        $response = $this->search('/open-teams/search', [['relation' => 'tickets.tags', 'type' => 'count', 'filters' => [['field' => 'title', 'value' => 'Printer jam']]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.aggregates.0.filters.0.field' => [$message]]]);
    }
}

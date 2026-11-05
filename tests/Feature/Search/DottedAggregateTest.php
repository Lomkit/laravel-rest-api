<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DottedAggregateTest extends PackageTestCase
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

    #[Test]
    public function it_leaves_out_the_rows_outside_the_perimeter_of_each_level(): void
    {
        $response = $this->search('/open-teams/search', [['relation' => 'tickets.tags', 'type' => 'count']]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'tickets_tags_count' => 1],
            ['id' => 2, 'tickets_tags_count' => 1],
            ['id' => 3, 'tickets_tags_count' => 0],
        ]);
    }

    /**
     * @param  array<string, string>  $aggregate
     * @param  list<float|bool|null>  $values
     */
    #[Test]
    #[DataProvider('functions')]
    public function it_aggregates_each_row_at_the_end_of_a_path_once(array $aggregate, string $key, array $values): void
    {
        $response = $this->search('/open-tickets/search', [['relation' => 'labels.labelledTickets', ...$aggregate]]);

        $response->assertOk();
        $this->assertSame([1, 2, 4, 5], array_column($response->json('data'), 'id'));
        $this->assertSame($values, array_map(static fn (mixed $value): float|bool|null => is_bool($value) || $value === null ? $value : (float) $value, array_column($response->json('data'), $key)));
    }

    /** @return array<string, array{array<string, string>, string, list<float|bool|null>}> */
    public static function functions(): array
    {
        return [
            'count' => [['type' => 'count'], 'labels_labelled_tickets_count', [3.0, 0.0, 3.0, 3.0]],
            'sum' => [['type' => 'sum', 'field' => 'team_id'], 'labels_labelled_tickets_sum_team_id', [6.0, null, 6.0, 6.0]],
            'avg' => [['type' => 'avg', 'field' => 'team_id'], 'labels_labelled_tickets_avg_team_id', [2.0, null, 2.0, 2.0]],
            'min' => [['type' => 'min', 'field' => 'team_id'], 'labels_labelled_tickets_min_team_id', [1.0, null, 1.0, 1.0]],
            'max' => [['type' => 'max', 'field' => 'team_id'], 'labels_labelled_tickets_max_team_id', [3.0, null, 3.0, 3.0]],
            'exists' => [['type' => 'exists'], 'labels_labelled_tickets_exists', [true, false, true, true]],
        ];
    }

    #[Test]
    public function it_aggregates_a_path_of_three_relations(): void
    {
        $response = $this->search('/open-teams/search', [['relation' => 'tickets.tags.labelledTickets', 'type' => 'count']]);

        $response->assertOk();
        $this->assertSame([2, 3, 0], array_column($response->json('data'), 'tickets_tags_labelled_tickets_count'));
    }

    #[Test]
    public function it_sorts_by_the_key_of_a_path_aggregate(): void
    {
        $response = $this->postJson('/open-teams/search', ['search' => [
            'selects' => [['field' => 'id']],
            'aggregates' => [['relation' => 'tickets.tags.labelledTickets', 'type' => 'count', 'alias' => 'reach']],
            'sorts' => [['field' => 'reach', 'direction' => 'desc']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 2, 'reach' => 3], ['id' => 1, 'reach' => 2], ['id' => 3, 'reach' => 0]]);
    }

    #[Test]
    public function it_sorts_the_rows_of_a_limited_include_by_a_path_aggregate(): void
    {
        $response = $this->postJson('/open-teams/search', ['search' => [
            'selects' => [['field' => 'id']],
            'filters' => [['field' => 'id', 'value' => 1]],
            'includes' => [[
                'relation' => 'tickets',
                'limit' => 1,
                'selects' => [['field' => 'id']],
                'aggregates' => [['relation' => 'labels.labelledTickets', 'type' => 'count', 'filters' => [['field' => 'status', 'value' => 'open']]]],
                'sorts' => [['field' => 'labels_labelled_tickets_count', 'direction' => 'desc']],
            ]],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, 'tickets' => [['id' => 1, 'labels_labelled_tickets_count' => 3]]]]);
    }

    #[Test]
    #[WithConfig('rest.search.select_columns', false)]
    public function it_aggregates_a_path_next_to_every_column(): void
    {
        $response = $this->postJson('/open-teams/search', ['search' => [
            'filters' => [['field' => 'id', 'value' => 1]],
            'aggregates' => [['relation' => 'tickets.tags', 'type' => 'count']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, 'name' => 'Core', 'tickets_tags_count' => 1]]);
    }
}

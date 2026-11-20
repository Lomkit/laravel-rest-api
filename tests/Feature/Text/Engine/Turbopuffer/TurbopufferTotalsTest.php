<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Turbopuffer;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Laravel\Scout\Engines\TurbopufferEngine;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherResource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('turbopuffer')]
class TurbopufferTotalsTest extends TurbopufferTestCase
{
    private const array FILTERS = ['And', [['__soft_deleted', 'Eq', 0], ['active', 'Eq', true]]];

    /** @var array<string, mixed> */
    private array $groups = [];

    /** @var array<string, mixed> */
    private array $aggregations = ['rest_count' => 4, 'rest_measure_0' => 50.75, 'rest_measure_1' => 2.5, 'rest_measure_2' => 4];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(static function (): void {
            Rest::resource('totalled-vouchers', TotalledVoucherController::class)->withSoftDeletes()->register();
        });
        $this->rows(['V-c', 'V-b'], 4);
        $this->groups = [
            'team' => [['team' => 'blue', 'rest_count' => 1], ['team' => 'red', 'rest_count' => 3]],
            'number' => [['number' => 1, 'rest_count' => 1], ['number' => 2, 'rest_count' => 1], ['number' => 3, 'rest_count' => 1], ['number' => 4, 'rest_count' => 1]],
        ];
    }

    #[Test]
    public function it_aggregates_the_rows_that_the_filters_of_the_list_keep(): void
    {
        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'filters' => [['field' => 'active', 'value' => true]], 'totals' => [
            'measures' => [['type' => 'sum', 'field' => 'price'], ['type' => 'avg', 'field' => 'number'], ['type' => 'max', 'field' => 'number']],
            'groups' => [['field' => 'team'], ['field' => 'number', 'limit' => 2]],
        ]]);

        $response->assertOk()->assertJsonPath('total', 4);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 4, 'sum_price' => 50.75, 'avg_number' => 2.5, 'max_number' => 4],
            'groups' => [
                ['field' => 'team', 'buckets' => [['value' => 'red', 'count' => 3], ['value' => 'blue', 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'number', 'buckets' => [['value' => 1, 'count' => 1], ['value' => 2, 'count' => 1]], 'other' => ['count' => 2], 'truncated' => true],
            ],
        ], $response->json('totals'));
        $this->assertSame([
            ['aggregate_by' => ['rest_count' => ['Count'], 'rest_measure_0' => ['Sum', 'price'], 'rest_measure_1' => ['Avg', 'number'], 'rest_measure_2' => ['Max', 'number']], 'filters' => self::FILTERS, 'consistency' => ['level' => 'strong']],
            ['aggregate_by' => ['rest_count' => ['Count']], 'group_by' => ['team'], 'limit' => 9999, 'filters' => self::FILTERS, 'consistency' => ['level' => 'strong']],
            ['aggregate_by' => ['rest_count' => ['Count']], 'group_by' => ['number'], 'limit' => 9999, 'filters' => self::FILTERS, 'consistency' => ['level' => 'strong']],
        ], array_values(array_filter($this->queries(), static fn (array $query): bool => isset($query['aggregate_by']['rest_count']))));
    }

    #[Test]
    public function it_returns_null_for_a_bound_that_the_engine_omits(): void
    {
        $this->aggregations = ['rest_count' => 4, 'rest_measure_0' => 50.75];

        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'filters' => [['field' => 'active', 'value' => true]], 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price'], ['type' => 'min', 'field' => 'price']]]]);

        $response->assertOk()->assertJsonPath('totals.all', ['count' => 4, 'sum_price' => 50.75, 'min_price' => null]);
    }

    #[Test]
    public function it_refuses_totals_for_a_text_search_whose_count_the_engine_does_not_filter_by_the_text(): void
    {
        $response = $this->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => ['groups' => [['field' => 'team']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is refused: the search engine of this resource computes totals for a list without text only.']]);
    }

    #[Test]
    public function it_refuses_a_group_with_more_values_than_one_aggregation_returns(): void
    {
        $this->groups['team'] = array_map(static fn (int $index): array => ['team' => "team-{$index}", 'rest_count' => 1], range(1, 9999));

        $this->expectExceptionObject(EngineSearchException::cappedFacetValues(TotalledVoucherResource::class, 'team', 9999));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'team']]]]);
    }

    #[Test]
    public function it_rounds_decimal_measures_to_the_scale_of_the_cast(): void
    {
        $this->aggregations = ['rest_count' => 4, 'rest_measure_0' => 50.749999237060547, 'rest_measure_1' => 1.5000001];

        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price'], ['type' => 'min', 'field' => 'price']]]]);

        $response->assertOk()->assertJsonPath('totals.all', ['count' => 4, 'sum_price' => 50.75, 'min_price' => 1.5]);
    }

    #[Test]
    public function it_sends_at_most_eight_aggregate_functions_per_query(): void
    {
        $measures = [];

        foreach (['price', 'number'] as $field) {
            foreach (['sum', 'avg', 'min', 'max'] as $type) {
                $measures[] = ['type' => $type, 'field' => $field];
            }
        }

        $this->aggregations = ['rest_count' => 4, 'rest_measure_0' => 1, 'rest_measure_1' => 1, 'rest_measure_2' => 1, 'rest_measure_3' => 1, 'rest_measure_4' => 1, 'rest_measure_5' => 1, 'rest_measure_6' => 1, 'rest_measure_7' => 5];

        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['measures' => $measures]]);

        $response->assertOk()->assertJsonPath('totals.all.max_number', 5);
        $this->assertSame([
            ['aggregate_by' => ['rest_count' => ['Count'], 'rest_measure_0' => ['Sum', 'price'], 'rest_measure_1' => ['Avg', 'price'], 'rest_measure_2' => ['Min', 'price'], 'rest_measure_3' => ['Max', 'price'], 'rest_measure_4' => ['Sum', 'number'], 'rest_measure_5' => ['Avg', 'number'], 'rest_measure_6' => ['Min', 'number']], 'filters' => ['__soft_deleted', 'Eq', 0], 'consistency' => ['level' => 'strong']],
            ['aggregate_by' => ['rest_measure_7' => ['Max', 'number']], 'filters' => ['__soft_deleted', 'Eq', 0], 'consistency' => ['level' => 'strong']],
        ], array_values(array_filter($this->queries(), static fn (array $query): bool => isset($query['aggregate_by']['rest_count']) || isset($query['aggregate_by']['rest_measure_7']))));
    }

    #[Test]
    public function it_refuses_an_answer_without_a_count(): void
    {
        $this->aggregations = ['rest_measure_0' => 50.75];

        $this->expectExceptionObject(EngineSearchException::missingCount(TotalledVoucherResource::class, TurbopufferEngine::class));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price']]]]);
    }

    #[Test]
    public function it_refuses_a_group_that_counts_more_rows_than_the_list(): void
    {
        $this->groups['team'] = [['team' => 'blue', 'rest_count' => 2], ['team' => 'red', 'rest_count' => 3]];

        $this->expectExceptionObject(EngineSearchException::multiValuedFacet(TotalledVoucherResource::class, 'team'));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'team']]]]);
    }

    #[\Override]
    protected function aggregated(array $query): ?array
    {
        $aggregates = is_array($query['aggregate_by'] ?? null) ? $query['aggregate_by'] : [];

        if (! isset($aggregates['rest_count'])) {
            return null;
        }

        $group = is_array($query['group_by'] ?? null) ? $query['group_by'][0] : null;

        if (is_string($group)) {
            return ['aggregation_groups' => $this->groups[$group] ?? []];
        }

        return ['aggregations' => $this->aggregations];
    }
}

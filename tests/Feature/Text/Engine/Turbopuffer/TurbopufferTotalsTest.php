<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Turbopuffer;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\EngineSearchException;
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
            ['aggregate_by' => ['rest_count' => ['Count']], 'group_by' => ['team'], 'limit' => 10000, 'filters' => self::FILTERS, 'consistency' => ['level' => 'strong']],
            ['aggregate_by' => ['rest_count' => ['Count']], 'group_by' => ['number'], 'limit' => 10000, 'filters' => self::FILTERS, 'consistency' => ['level' => 'strong']],
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
        $this->groups['team'] = array_map(static fn (int $index): array => ['team' => "team-{$index}", 'rest_count' => 1], range(1, 10000));

        $this->expectExceptionObject(EngineSearchException::cappedFacetValues(TotalledVoucherResource::class, 'team', 10000));

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

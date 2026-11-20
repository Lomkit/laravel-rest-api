<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\FacetingMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherResource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

#[Group('scout')]
class MeilisearchTotalsTest extends EngineSearchTestCase
{
    private const array RESULT = [
        'facetDistribution' => [
            'team' => ['blue' => 1, 'red' => 4],
            'active' => ['false' => 1, 'true' => 4],
            'number' => ['1' => 1, '2' => 1, '3' => 1, '4' => 1, '5' => 1],
            'price' => ['1.5' => 1, '7.25' => 1, '12' => 1, '30' => 1, '40' => 1],
        ],
        'facetStats' => ['number' => ['min' => 1, 'max' => 5], 'price' => ['min' => 1.5, 'max' => 40]],
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(static function (): void {
            Rest::resource('totalled-vouchers', TotalledVoucherController::class)->register();
        });
    }

    #[Test]
    public function it_reads_the_facet_distribution_and_stats_of_the_search_of_the_page(): void
    {
        $engine = $this->engine();

        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'totals' => [
            'measures' => [['type' => 'min', 'field' => 'price'], ['type' => 'max', 'field' => 'number']],
            'groups' => [['field' => 'team', 'limit' => 1], ['field' => 'active'], ['field' => 'number', 'limit' => 2]],
        ]]);

        $response->assertOk()->assertJsonPath('total', 5);
        $this->assertSame(['team', 'active', 'number', 'price'], $engine->options[0]['facets']);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 5, 'min_price' => 1.5, 'max_number' => 5],
            'groups' => [
                ['field' => 'team', 'buckets' => [['value' => 'red', 'count' => 4]], 'other' => ['count' => 1], 'truncated' => true],
                ['field' => 'active', 'buckets' => [['value' => true, 'count' => 4], ['value' => false, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'number', 'buckets' => [['value' => 1, 'count' => 1], ['value' => 2, 'count' => 1]], 'other' => ['count' => 3], 'truncated' => true],
            ],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_refuses_the_sum_and_the_average_that_the_engine_does_not_compute(): void
    {
        $this->engine();

        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price'], ['type' => 'avg', 'field' => 'number'], ['type' => 'max', 'field' => 'number']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.totals.measures.0.type' => ['The search.totals.measures.0.type field holds a measure that the search engine of this resource does not compute on this field.'],
            'search.totals.measures.1.type' => ['The search.totals.measures.1.type field holds a measure that the search engine of this resource does not compute on this field.'],
        ]);
    }

    #[Test]
    public function it_refuses_a_group_whose_values_the_index_may_cut_without_sorting_them_by_count(): void
    {
        $this->engine(['maxValuesPerFacet' => 4, 'sortFacetValuesBy' => ['*' => 'alpha']]);

        $this->expectExceptionObject(EngineSearchException::cappedFacetValues(TotalledVoucherResource::class, 'number', 4));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'team'], ['field' => 'number', 'limit' => 2]]]]);
    }

    #[Test]
    public function it_trusts_a_cut_list_that_the_index_sorts_by_count_when_the_limit_fits_in_it(): void
    {
        $this->engine(['maxValuesPerFacet' => 4, 'sortFacetValuesBy' => ['*' => 'alpha', 'number' => 'count']]);

        $fits = $this->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'number', 'limit' => 3]]]]);

        $fits->assertOk()->assertJsonPath('totals.groups.0', ['field' => 'number', 'buckets' => [['value' => 1, 'count' => 1], ['value' => 2, 'count' => 1], ['value' => 3, 'count' => 1]], 'other' => ['count' => 2], 'truncated' => true]);
        $this->expectExceptionObject(EngineSearchException::cappedFacetValues(TotalledVoucherResource::class, 'number', 4));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'number', 'limit' => 4]]]]);
    }

    #[Test]
    public function it_names_an_attribute_that_the_index_does_not_facet(): void
    {
        $engine = $this->engine();
        $engine->refusal = 'Invalid facet distribution, attribute `team` is not filterable. The available filterable attributes are `active, code, number, price`.';

        try {
            $this->withoutExceptionHandling()->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'active'], ['field' => 'team']]]]);
            $this->fail('The search passed.');
        } catch (EngineSearchException $exception) {
            $this->assertSame(EngineSearchException::missingFacet(TotalledVoucherResource::class, 'team', FacetingMeilisearchEngine::class)->getMessage(), $exception->getMessage());
            $this->assertSame($engine->refusal, $exception->getPrevious()?->getMessage());
        }
    }

    #[Test]
    public function it_refuses_totals_when_the_faceting_settings_cannot_be_read(): void
    {
        $this->engine(null);

        try {
            $this->withoutExceptionHandling()->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'team']]]]);
            $this->fail('The search passed.');
        } catch (EngineSearchException $exception) {
            $this->assertSame(EngineSearchException::unreadFaceting(TotalledVoucherResource::class, FacetingMeilisearchEngine::class, new RuntimeException('The API key cannot read the settings.'))->getMessage(), $exception->getMessage());
        }
    }

    /** @param array<string, mixed>|null $faceting */
    private function engine(?array $faceting = ['maxValuesPerFacet' => 100, 'sortFacetValuesBy' => ['*' => 'alpha']]): FacetingMeilisearchEngine
    {
        $engine = new FacetingMeilisearchEngine(self::RESULT, $faceting);
        $this->useEngine($engine);

        return $engine;
    }
}

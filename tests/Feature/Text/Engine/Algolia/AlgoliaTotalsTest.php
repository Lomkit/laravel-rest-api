<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Algolia;

use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\Algolia4Engine;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('algolia')]
class AlgoliaTotalsTest extends AlgoliaTestCase
{
    private const array TOTALS = [
        'measures' => [['type' => 'sum', 'field' => 'price'], ['type' => 'min', 'field' => 'number'], ['type' => 'max', 'field' => 'number']],
        'groups' => [['field' => 'team', 'limit' => 1], ['field' => 'active']],
    ];

    private const array FACETS = [
        'facets' => ['team' => ['red' => 3, 'blue' => 1], 'active' => ['true' => 3, 'false' => 1], 'price' => ['1.5' => 1, '7.25' => 1, '12' => 1, '40' => 1], 'number' => ['1' => 1, '3' => 1, '4' => 1, '5' => 1]],
        'facets_stats' => ['price' => ['min' => 1.5, 'max' => 40, 'avg' => 15.19, 'sum' => 60.75], 'number' => ['min' => 1, 'max' => 5, 'avg' => 3.25, 'sum' => 13]],
        'exhaustiveFacetsCount' => true,
        'exhaustive' => ['facetsCount' => true, 'facetValues' => true, 'nbHits' => true],
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(static function (): void {
            Rest::resource('totalled-vouchers', TotalledVoucherController::class)->withSoftDeletes()->register();
        });
    }

    #[Test]
    public function it_asks_the_search_of_the_page_for_its_facets_sorted_by_count(): void
    {
        $this->facets(self::FACETS);

        $response = $this->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => self::TOTALS]);

        $response->assertOk()->assertJsonPath('total', 4);
        $this->assertSame([['POST', "/1/indexes/{$this->index}/query", [
            'query' => 'report',
            'enableRules' => false,
            'facets' => ['team', 'active', 'price', 'number'],
            'maxValuesPerFacet' => 101,
            'sortFacetValuesBy' => 'count',
            'facetingAfterDistinct' => true,
            'filters' => "__soft_deleted:'0'",
            'hitsPerPage' => 50,
            'page' => 0,
        ]]], $this->algolia->sent('POST', "/1/indexes/{$this->index}/query"));
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 4, 'sum_price' => 60.75, 'min_number' => 1, 'max_number' => 5],
            'groups' => [
                ['field' => 'team', 'buckets' => [['value' => 'red', 'count' => 3]], 'other' => ['count' => 1], 'truncated' => true],
                ['field' => 'active', 'buckets' => [['value' => true, 'count' => 3], ['value' => false, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
            ],
        ], $response->json('totals'));
    }

    /** @param array<string, mixed> $flags */
    #[Test]
    #[DataProvider('partialAnswers')]
    public function it_refuses_facets_that_the_engine_did_not_count_exhaustively(array $flags): void
    {
        $this->facets([...self::FACETS, ...$flags]);

        $this->expectExceptionObject(EngineSearchException::partialFacets(TotalledVoucherResource::class, Algolia4Engine::class));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => self::TOTALS]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function partialAnswers(): array
    {
        return [
            'an approximate facet count' => [['exhaustiveFacetsCount' => false]],
            'an approximate hit count' => [['exhaustiveNbHits' => false]],
            'approximate facet values' => [['exhaustive' => ['facetsCount' => true, 'facetValues' => false, 'nbHits' => true]]],
            'approximate facets in the exhaustive block' => [['exhaustive' => ['facetsCount' => false, 'facetValues' => true, 'nbHits' => true]]],
        ];
    }

    #[Test]
    public function it_refuses_a_declared_attribute_that_the_index_does_not_facet(): void
    {
        $this->facets([...self::FACETS, 'facets' => ['active' => ['true' => 3, 'false' => 1]]]);

        $this->expectExceptionObject(EngineSearchException::missingFacet(TotalledVoucherResource::class, 'team', Algolia4Engine::class));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => ['groups' => [['field' => 'team'], ['field' => 'active']]]]);
    }

    #[Test]
    public function it_refuses_the_average_that_the_engine_truncates_on_integers(): void
    {
        $response = $this->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => ['measures' => [['type' => 'avg', 'field' => 'number'], ['type' => 'avg', 'field' => 'price']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.totals.measures.0.type' => ['The search.totals.measures.0.type field holds a measure that the search engine of this resource does not compute on this field.'],
            'search.totals.measures.1.type' => ['The search.totals.measures.1.type field holds a measure that the search engine of this resource does not compute on this field.'],
        ]);
    }

    #[Test]
    public function it_bounds_the_group_limit_to_the_values_that_the_engine_returns(): void
    {
        config(['rest.limits.sizes.groups' => 2000]);

        $response = $this->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => ['groups' => [['field' => 'team', 'limit' => 1000]]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals.groups.0.limit' => ['The search.totals.groups.0.limit field must not be greater than 999.']]);
    }

    /** @param array<string, mixed> $facets */
    private function facets(array $facets): void
    {
        $this->algolia->respond('POST', "/1/indexes/{$this->index}/query", 200, [
            'hits' => [['code' => 'V-a', 'objectID' => 'V-a', '_highlightResult' => []]],
            'nbHits' => 4,
            'page' => 0,
            'nbPages' => 1,
            'hitsPerPage' => 50,
            'exhaustiveNbHits' => true,
            'query' => 'report',
            'params' => '',
            'processingTimeMS' => 1,
            ...$facets,
        ]);
    }
}

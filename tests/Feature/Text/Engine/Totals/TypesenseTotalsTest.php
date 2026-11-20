<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\CentsVoucher;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\FacetingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TypesenseSchemas;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

#[Group('scout')]
class TypesenseTotalsTest extends EngineSearchTestCase
{
    private const array TOTALS = [
        'measures' => [['type' => 'sum', 'field' => 'price'], ['type' => 'avg', 'field' => 'number'], ['type' => 'max', 'field' => 'number']],
        'groups' => [['field' => 'team', 'limit' => 1], ['field' => 'active']],
    ];

    private const array FACETS = [
        ['field_name' => 'team', 'sampled' => false, 'counts' => [['value' => 'red', 'count' => 3, 'highlighted' => 'red'], ['value' => 'blue', 'count' => 1, 'highlighted' => 'blue']], 'stats' => ['total_values' => 2]],
        ['field_name' => 'active', 'sampled' => false, 'counts' => [['value' => 'true', 'count' => 3, 'highlighted' => 'true'], ['value' => 'false', 'count' => 1, 'highlighted' => 'false']], 'stats' => ['total_values' => 2]],
        ['field_name' => 'price', 'sampled' => false, 'counts' => [['value' => '1.5', 'count' => 1, 'highlighted' => '1.5']], 'stats' => ['min' => 1.5, 'max' => 40, 'sum' => 50.75, 'avg' => 12.6875, 'total_values' => 4]],
        ['field_name' => 'number', 'sampled' => false, 'counts' => [['value' => '17', 'count' => 1, 'highlighted' => '17']], 'stats' => ['min' => 1, 'max' => 5, 'sum' => 13, 'avg' => 3.25, 'total_values' => 4]],
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(static function (): void {
            Rest::resource('totalled-vouchers', TotalledVoucherController::class)->register();
        });
        config(['scout.typesense.model-settings.'.CentsVoucher::class.'.search-parameters.query_by' => 'title']);
    }

    #[Test]
    public function it_asks_the_search_of_the_page_for_exhaustive_unsampled_facets(): void
    {
        $engine = $this->engine();

        $response = $this->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => self::TOTALS]);

        $response->assertOk();
        $this->assertSame('search', $engine->requests[0][0]);
        $this->assertSame([
            'filter_curated_hits' => true,
            'facet_by' => 'team,active,price,number',
            'max_facet_values' => 101,
            'facet_strategy' => 'exhaustive',
            'facet_sample_percent' => 100,
            'facet_sample_threshold' => 0,
        ], array_intersect_key($engine->requests[0][1], array_flip(['facet_by', 'max_facet_values', 'facet_strategy', 'facet_sample_percent', 'facet_sample_threshold', 'filter_curated_hits'])));
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 4, 'sum_price' => 50.75, 'avg_number' => 3.25, 'max_number' => 5],
            'groups' => [
                ['field' => 'team', 'buckets' => [['value' => 'red', 'count' => 3]], 'other' => ['count' => 1], 'truncated' => true],
                ['field' => 'active', 'buckets' => [['value' => true, 'count' => 3], ['value' => false, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
            ],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_sends_the_same_facets_through_multi_search_for_a_long_filter(): void
    {
        $engine = $this->engine();
        $codes = array_map(static fn (int $index): string => sprintf('V-%040d', $index), range(1, 100));

        $response = $this->search('totalled-vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'code', 'operator' => 'in', 'value' => $codes]], 'totals' => ['groups' => [['field' => 'number', 'limit' => 3]]]]);

        $response->assertOk()->assertJsonPath('totals.groups', [['field' => 'number', 'buckets' => [['value' => 17, 'count' => 1]], 'other' => ['count' => 3], 'truncated' => false]]);
        $search = $engine->requests[0][1]['searches'][0];
        $this->assertSame('multi_search', $engine->requests[0][0]);
        $this->assertSame(['number', 4, 'exhaustive', 100, 0, true], [$search['facet_by'], $search['max_facet_values'], $search['facet_strategy'], $search['facet_sample_percent'], $search['facet_sample_threshold'], $search['filter_curated_hits']]);
    }

    #[Test]
    public function it_sends_no_facet_to_a_search_without_totals(): void
    {
        $engine = $this->engine();

        $this->search('totalled-vouchers', ['text' => ['value' => 'report']])->assertOk()->assertJsonMissingPath('totals');

        $this->assertSame([], array_intersect_key($engine->requests[0][1], array_flip(['facet_by', 'max_facet_values', 'facet_strategy'])));
    }

    #[Test]
    public function it_refuses_totals_that_the_search_engine_cut_off(): void
    {
        $this->engine(['facet_counts' => [], 'search_cutoff' => true]);

        $this->expectExceptionObject(EngineSearchException::partialFacets(TotalledVoucherResource::class, FacetingTypesenseEngine::class));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => self::TOTALS]);
    }

    #[Test]
    public function it_refuses_sampled_facets(): void
    {
        $this->engine(['facet_counts' => [['field_name' => 'team', 'sampled' => true, 'counts' => []], ...array_slice(self::FACETS, 1)]]);

        $this->expectExceptionObject(EngineSearchException::partialFacets(TotalledVoucherResource::class, FacetingTypesenseEngine::class));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => self::TOTALS]);
    }

    #[Test]
    public function it_refuses_a_group_that_counts_more_values_than_rows(): void
    {
        $this->engine(['found' => 3]);

        $this->expectExceptionObject(EngineSearchException::multiValuedFacet(TotalledVoucherResource::class, 'team'));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => ['groups' => [['field' => 'team']]]]);
    }

    #[Test]
    public function it_refuses_a_declared_attribute_that_the_facets_leave_out(): void
    {
        $this->engine(['facet_counts' => array_slice(self::FACETS, 1)]);

        $this->expectExceptionObject(EngineSearchException::missingFacet(TotalledVoucherResource::class, 'team', FacetingTypesenseEngine::class));

        $this->withoutExceptionHandling()->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => self::TOTALS]);
    }

    #[Test]
    public function it_names_the_attribute_that_typesense_cannot_facet_on_a_get_search(): void
    {
        $engine = $this->engine();
        $engine->refusal = 'Could not find a facet field named `active` in the schema.';

        try {
            $this->withoutExceptionHandling()->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => self::TOTALS]);
            $this->fail('The search passed.');
        } catch (EngineSearchException $exception) {
            $this->assertSame(EngineSearchException::missingFacet(TotalledVoucherResource::class, 'active', FacetingTypesenseEngine::class)->getMessage(), $exception->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
            $this->assertSame($engine->refusal, $exception->getPrevious()->getMessage());
        }
    }

    /** @param list<array<string, mixed>>|null $fields */
    #[Test]
    #[DataProvider('schemas')]
    public function it_reads_the_collection_schema_before_it_trusts_the_facets(?array $fields, string $expected): void
    {
        $engine = $this->engine();
        $engine->schemas = new TypesenseSchemas($fields);

        try {
            $this->withoutExceptionHandling()->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price']], 'groups' => [['field' => 'team', 'limit' => 1]]]]);
            $this->fail('The search passed.');
        } catch (EngineSearchException $exception) {
            $this->assertSame($expected, $exception->getMessage());
        }

        $this->assertSame([], $engine->requests);
    }

    /** @return array<string, array{list<array<string, mixed>>|null, string}> */
    public static function schemas(): array
    {
        $fields = static fn (array $team, array $price): array => [['name' => 'team', ...$team], ['name' => 'price', ...$price]];

        return [
            'a grouped list of strings' => [$fields(['type' => 'string[]', 'facet' => true], ['type' => 'float', 'facet' => true]), EngineSearchException::multiValuedFacet(TotalledVoucherResource::class, 'team')->getMessage()],
            'a grouped attribute without facet' => [$fields(['type' => 'string', 'facet' => false], ['type' => 'float', 'facet' => true]), EngineSearchException::missingFacet(TotalledVoucherResource::class, 'team', FacetingTypesenseEngine::class)->getMessage()],
            'a grouped attribute that the schema leaves out' => [[['name' => 'price', 'type' => 'float', 'facet' => true]], EngineSearchException::missingFacet(TotalledVoucherResource::class, 'team', FacetingTypesenseEngine::class)->getMessage()],
            'a measured string' => [$fields(['type' => 'string', 'facet' => true], ['type' => 'string', 'facet' => true]), EngineSearchException::unmeasurableFacet(TotalledVoucherResource::class, 'price', 'string')->getMessage()],
            'an unreadable schema' => [null, EngineSearchException::unreadSchema(TotalledVoucherResource::class, FacetingTypesenseEngine::class)->getMessage()],
        ];
    }

    #[Test]
    public function it_matches_a_regular_expression_field_of_the_schema(): void
    {
        $engine = $this->engine();
        $engine->schemas = new TypesenseSchemas([['name' => '.*', 'type' => 'auto', 'facet' => false], ['name' => 'te.*', 'type' => 'string', 'facet' => true]]);

        $this->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => ['groups' => [['field' => 'team']]]])->assertOk();

        $this->assertCount(1, $engine->schemas->read);
    }

    #[Test]
    public function it_reads_no_facet_from_an_answer_without_a_hit(): void
    {
        $this->engine(['hits' => [], 'found' => 0, 'facet_counts' => []]);

        $response = $this->search('totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price']], 'groups' => [['field' => 'team']]]]);

        $response->assertOk()->assertJsonPath('totals', ['exact' => true, 'all' => ['count' => 0, 'sum_price' => 0], 'groups' => [['field' => 'team', 'buckets' => [], 'other' => ['count' => 0], 'truncated' => false]]]);
    }

    #[Test]
    public function it_returns_empty_totals_for_a_text_without_a_term_without_calling_the_engine(): void
    {
        $engine = $this->engine();

        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'text' => ['value' => '***'], 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price']], 'groups' => [['field' => 'team']]]]);

        $response->assertOk()->assertJsonPath('totals', ['exact' => true, 'all' => ['count' => 0, 'sum_price' => 0], 'groups' => [['field' => 'team', 'buckets' => [], 'other' => ['count' => 0], 'truncated' => false]]]);
        $this->assertSame([], $engine->requests);
    }

    /** @param array<string, mixed> $result */
    private function engine(array $result = []): FacetingTypesenseEngine
    {
        $engine = new FacetingTypesenseEngine([
            'hits' => [['document' => ['id' => 'V-a', 'code' => 'V-a']]],
            'found' => 4,
            'facet_counts' => self::FACETS,
            ...$result,
        ]);
        $this->useEngine($engine);

        return $engine;
    }
}

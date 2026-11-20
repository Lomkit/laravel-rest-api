<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Meilisearch;

use Illuminate\Support\Facades\Route;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\MeilisearchEngine;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\CentsVoucher;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\PerimeterTotalledVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\PerimeterTotalledVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Meilisearch\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('meilisearch')]
class MeilisearchTotalsTest extends MeilisearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(static function (): void {
            Rest::resource('perimeter-totalled-vouchers', PerimeterTotalledVoucherController::class)->register();
        });
    }

    #[Test]
    public function it_computes_the_counts_and_bounds_of_the_rows_that_the_perimeter_keeps_as_sql_does(): void
    {
        $response = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => [
            'measures' => [['type' => 'min', 'field' => 'price'], ['type' => 'max', 'field' => 'price'], ['type' => 'min', 'field' => 'number'], ['type' => 'max', 'field' => 'number']],
            'groups' => [['field' => 'active'], ['field' => 'number', 'limit' => 2]],
        ]]);
        $rows = Voucher::query()->where('team', 'red')->get();

        $response->assertOk()->assertJsonPath('total', $rows->count());
        $this->assertSame([4, 1.5, 40.0, 1, 5], [$rows->count(), $rows->min('price'), $rows->max('price'), $rows->min('number'), $rows->max('number')]);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 4, 'min_price' => 1.5, 'max_price' => 40, 'min_number' => 1, 'max_number' => 5],
            'groups' => [
                ['field' => 'active', 'buckets' => [['value' => true, 'count' => 3], ['value' => false, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'number', 'buckets' => [['value' => 1, 'count' => 1], ['value' => 2, 'count' => 1]], 'other' => ['count' => 2], 'truncated' => true],
            ],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_computes_the_totals_of_a_text_search(): void
    {
        $response = $this->search('perimeter-totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => ['measures' => [['type' => 'max', 'field' => 'price']], 'groups' => [['field' => 'active', 'limit' => 1]]]]);

        $response->assertOk()->assertJsonPath('total', 3);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 3, 'max_price' => 40],
            'groups' => [['field' => 'active', 'buckets' => [['value' => true, 'count' => 2]], 'other' => ['count' => 1], 'truncated' => true]],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_refuses_the_sum_that_the_engine_does_not_compute(): void
    {
        $response = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals.measures.0.type' => ['The search.totals.measures.0.type field holds a measure that the search engine of this resource does not compute on this field.']]);
    }

    #[Test]
    public function it_refuses_a_group_that_the_index_cuts_in_alphabetical_order_and_trusts_one_sorted_by_count(): void
    {
        $this->indexes->configure($this->index, ['faceting' => ['maxValuesPerFacet' => 2, 'sortFacetValuesBy' => ['*' => 'alpha', 'active' => 'count']]]);

        $sorted = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'active', 'limit' => 1]]]]);

        $sorted->assertOk()->assertJsonPath('totals.groups', [['field' => 'active', 'buckets' => [['value' => true, 'count' => 3]], 'other' => ['count' => 1], 'truncated' => true]]);
        $this->expectExceptionObject(EngineSearchException::cappedFacetValues(PerimeterTotalledVoucherResource::class, 'number', 2));

        $this->withoutExceptionHandling()->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'number', 'limit' => 1]]]]);
    }

    #[Test]
    public function it_bounds_decimal_values_exactly(): void
    {
        foreach ([1 => 19.99, 2 => 0.10, 3 => 1234.56, 5 => 19.99] as $id => $price) {
            CentsVoucher::query()->whereKey($id)->update(['price' => $price]);
        }

        CentsVoucher::query()->get()->searchable();
        $this->settle();

        $response = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => ['measures' => [['type' => 'min', 'field' => 'price'], ['type' => 'max', 'field' => 'price']]]]);

        $response->assertOk()->assertJsonPath('totals.all', ['count' => 4, 'min_price' => 0.1, 'max_price' => 1234.56]);
    }

    #[Test]
    public function it_returns_empty_totals_without_a_hit(): void
    {
        $response = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'filters' => [['field' => 'number', 'value' => 99]], 'totals' => ['measures' => [['type' => 'max', 'field' => 'price']], 'groups' => [['field' => 'active']]]]);

        $response->assertOk()->assertJsonPath('total', 0)->assertJsonPath('totals', ['exact' => true, 'all' => ['count' => 0, 'max_price' => null], 'groups' => [['field' => 'active', 'buckets' => [], 'other' => ['count' => 0], 'truncated' => false]]]);
    }

    #[Test]
    public function it_refuses_unreadable_faceting_settings(): void
    {
        $key = $this->indexes->key($this->index, ['search']);
        config(['scout.meilisearch.key' => $key]);
        $this->app->forgetInstance(Client::class);
        $this->app->make(EngineManager::class)->forgetEngines();
        $this->beforeApplicationDestroyed(fn () => $this->indexes->forget($key));

        try {
            $this->withoutExceptionHandling()->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'active']]]]);
            $this->fail('The search passed.');
        } catch (EngineSearchException $exception) {
            $this->assertStringStartsWith('The package could not read the faceting settings of the index of the resource ['.PerimeterTotalledVoucherResource::class.']', $exception->getMessage());
        }
    }

    #[Test]
    public function it_names_an_attribute_that_the_index_does_not_facet(): void
    {
        $this->indexes->configure($this->index, ['filterableAttributes' => ['number', 'price', 'code', 'team', 'title', '__soft_deleted']]);

        try {
            $this->withoutExceptionHandling()->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'active']]]]);
            $this->fail('The search passed.');
        } catch (EngineSearchException $exception) {
            $this->assertSame(EngineSearchException::missingFacet(PerimeterTotalledVoucherResource::class, 'active', MeilisearchEngine::class)->getMessage(), $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }
    }
}

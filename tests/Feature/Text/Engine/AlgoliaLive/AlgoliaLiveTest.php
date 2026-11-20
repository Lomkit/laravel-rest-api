<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\AlgoliaLive;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\Algolia4Engine;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Exceptions\SearchRunException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\PerimeterTotalledVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\PerimeterTotalledVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('algolia-live')]
class AlgoliaLiveTest extends AlgoliaLiveTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(static function (): void {
            Rest::resource('totalled-vouchers', TotalledVoucherController::class)->withSoftDeletes()->register();
            Rest::resource('perimeter-totalled-vouchers', PerimeterTotalledVoucherController::class)->withSoftDeletes()->register();
            Rest::resource('reader-vouchers', ReaderVoucherController::class)->register();
        });
    }

    #[Test]
    public function it_searches_the_text_with_filters_and_reads_the_hit_count(): void
    {
        $text = $this->search('unsorted-vouchers', ['text' => ['value' => 'report']]);
        $filtered = $this->search('unsorted-vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'price', 'operator' => '<', 'value' => 40], ['field' => 'number', 'operator' => 'in', 'value' => [1, 3, 4, 5]]]]);
        $termless = $this->search('unsorted-vouchers', ['text' => ['value' => '***']]);

        $text->assertOk()->assertJsonPath('total', 4)->assertJsonPath('dropped', 0);
        $this->assertEqualsCanonicalizing([1, 3, 4, 5], array_column($text->json('data'), 'id'));
        $filtered->assertOk()->assertJsonPath('total', 3);
        $this->assertEqualsCanonicalizing([1, 3, 4], array_column($filtered->json('data'), 'id'));
        $termless->assertOk()->assertJsonPath('total', 0)->assertJsonPath('data', []);
    }

    #[Test]
    public function it_lists_the_index_with_its_perimeter_and_pages_it(): void
    {
        $list = $this->search('totalled-vouchers', ['source' => 'engine']);
        $perimeter = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'limit' => 10, 'page' => 1]);
        $second = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'limit' => 2, 'page' => 2]);

        $list->assertOk()->assertJsonPath('total', 5);
        $perimeter->assertOk()->assertJsonPath('total', 4);
        $this->assertEqualsCanonicalizing([1, 2, 3, 5], array_column($perimeter->json('data'), 'id'));
        $second->assertOk()->assertJsonPath('total', 4)->assertJsonPath('current_page', 2);
        $this->assertCount(2, $second->json('data'));
    }

    #[Test]
    public function it_restricts_a_string_list_through_an_engine_restriction(): void
    {
        config(['rest.extensions' => [ReadersExtension::class]]);
        ReadersExtension::$groups = ['ann'];
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', 3);
    }

    #[Test]
    public function it_keeps_the_rules_of_the_index_off(): void
    {
        $this->wait($this->client->saveRule($this->index, 'promote-budget', ['objectID' => 'promote-budget', 'conditions' => [['pattern' => 'report', 'anchoring' => 'contains']], 'consequence' => ['promote' => [['objectID' => 'V-b', 'position' => 0]]]]));

        $response = $this->search('unsorted-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertJsonPath('total', 4);
        $this->assertNotContains(2, array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function it_reads_a_thousand_keys_per_page_and_runs_an_action_on_them(): void
    {
        DB::table('vouchers')->insert(array_map(static fn (int $id): array => ['id' => $id, 'code' => sprintf('V-%04d', $id), 'number' => $id, 'team' => 'red', 'title' => "Report {$id}", 'price' => 1, 'active' => true], range(7, 1106)));
        $this->index(ReaderVoucher::query()->where('id', '>=', 7)->orderBy('id')->get()->all());
        $this->configure(['paginationLimitedTo' => 2000]);

        $keys = Rest::search(UnsortedVoucherResource::class)->as(null)->payload(['text' => ['value' => 'report']])->lazy(1000)->pluck('id')->all();
        $action = $this->postJson('/api/unsorted-vouchers/actions/review-vouchers', ['search' => ['text' => ['value' => 'report'], 'filters' => [['field' => 'number', 'operator' => '<=', 'value' => 3]]]]);

        $this->assertCount(1104, $keys);
        $this->assertCount(1104, array_unique($keys));
        $action->assertOk()->assertJsonPath('data.impacted', 2);
    }

    #[Test]
    public function it_refuses_a_lazy_read_past_the_pagination_limit_of_the_index(): void
    {
        $this->configure(['paginationLimitedTo' => 2]);

        $this->expectExceptionObject(SearchRunException::engineWindow(UnsortedVoucherResource::class, 2));

        Rest::search(UnsortedVoucherResource::class)->as(null)->payload(['text' => ['value' => 'report']])->lazy()->all();
    }

    #[Test]
    public function it_computes_the_totals_of_the_rows_that_the_perimeter_keeps_as_sql_does(): void
    {
        $response = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => [
            'measures' => [['type' => 'sum', 'field' => 'number'], ['type' => 'sum', 'field' => 'price'], ['type' => 'min', 'field' => 'price'], ['type' => 'max', 'field' => 'price']],
            'groups' => [['field' => 'active'], ['field' => 'number', 'limit' => 2]],
        ]]);
        $rows = Voucher::query()->where('team', 'red')->get();

        $response->assertOk()->assertJsonPath('total', $rows->count());
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 4, 'sum_number' => 11, 'sum_price' => 78.75, 'min_price' => 1.5, 'max_price' => 40.0],
            'groups' => [
                ['field' => 'active', 'buckets' => [['value' => true, 'count' => 3], ['value' => false, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'number', 'buckets' => [['value' => 1, 'count' => 1], ['value' => 2, 'count' => 1]], 'other' => ['count' => 2], 'truncated' => true],
            ],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_computes_the_totals_of_a_text_search(): void
    {
        $response = $this->search('perimeter-totalled-vouchers', ['text' => ['value' => 'report'], 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price']], 'groups' => [['field' => 'active', 'limit' => 1]]]]);

        $response->assertOk()->assertJsonPath('total', 3);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 3, 'sum_price' => 48.75],
            'groups' => [['field' => 'active', 'buckets' => [['value' => true, 'count' => 2]], 'other' => ['count' => 1], 'truncated' => true]],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_measures_a_decimal_indexed_as_a_numeric_string_exactly(): void
    {
        $objects = array_map(static fn (ReaderVoucher $voucher): array => [...$voucher->toSearchableArray(), 'price' => number_format((float) $voucher->price, 2, '.', ''), 'objectID' => $voucher->getScoutKey(), '__soft_deleted' => 0], ReaderVoucher::query()->orderBy('id')->get()->all());
        $this->client->saveObjects($this->index, $objects, true);

        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price'], ['type' => 'max', 'field' => 'price']]]]);

        $response->assertOk()->assertJsonPath('totals.all', ['count' => 5, 'sum_price' => 90.75, 'max_price' => 40.0]);
    }

    #[Test]
    public function it_counts_rows_without_a_value_when_no_matched_row_has_one(): void
    {
        $objects = array_map(static fn (ReaderVoucher $voucher): array => [...array_diff_key($voucher->toSearchableArray(), ['team' => true]), 'objectID' => $voucher->getScoutKey(), '__soft_deleted' => 0], ReaderVoucher::query()->whereIn('code', ['V-a', 'V-c'])->get()->all());
        $this->client->saveObjects($this->index, $objects, true);

        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'filters' => [['field' => 'code', 'operator' => 'in', 'value' => ['V-a', 'V-c']]], 'totals' => ['measures' => [['type' => 'sum', 'field' => 'number']], 'groups' => [['field' => 'team']]]]);

        $response->assertOk()->assertJsonPath('total', 2)->assertJsonPath('totals', [
            'exact' => true,
            'all' => ['count' => 2, 'sum_number' => 4],
            'groups' => [['field' => 'team', 'buckets' => [], 'other' => ['count' => 2], 'truncated' => false]],
        ]);
    }

    #[Test]
    public function it_names_an_attribute_that_the_index_does_not_facet(): void
    {
        $this->configure(['attributesForFaceting' => ['team', 'number', 'price', 'code', 'filterOnly(readers)', 'filterOnly(__soft_deleted)']]);

        $this->expectExceptionObject(EngineSearchException::missingFacet(PerimeterTotalledVoucherResource::class, 'active', Algolia4Engine::class));

        $this->withoutExceptionHandling()->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'active']]]]);
    }
}

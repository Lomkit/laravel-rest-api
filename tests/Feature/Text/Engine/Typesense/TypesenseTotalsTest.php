<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\CentsVoucher;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\PerimeterTotalledVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\PerimeterTotalledVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TaggedTotalsVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TaggedTotalsVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('typesense')]
class TypesenseTotalsTest extends TypesenseTestCase
{
    private const array TOTALS = [
        'measures' => [
            ['type' => 'sum', 'field' => 'number'],
            ['type' => 'avg', 'field' => 'number'],
            ['type' => 'sum', 'field' => 'price'],
            ['type' => 'avg', 'field' => 'price'],
            ['type' => 'min', 'field' => 'price'],
            ['type' => 'max', 'field' => 'price'],
        ],
        'groups' => [['field' => 'active'], ['field' => 'number', 'limit' => 2]],
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(static function (): void {
            Rest::resource('perimeter-totalled-vouchers', PerimeterTotalledVoucherController::class)->register();
        });
    }

    #[Test]
    public function it_computes_the_totals_of_the_rows_that_the_perimeter_keeps_as_sql_does(): void
    {
        $response = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => self::TOTALS]);
        $rows = Voucher::query()->where('team', 'red')->get();

        $response->assertOk()->assertJsonPath('total', $rows->count());
        $this->assertSame([4, 11, 2.75, 78.75, 19.6875, 1.5, 40.0], [$rows->count(), (int) $rows->sum('number'), $rows->avg('number'), $rows->sum('price'), $rows->avg('price'), $rows->min('price'), $rows->max('price')]);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 4, 'sum_number' => 11, 'avg_number' => 2.75, 'sum_price' => 78.75, 'avg_price' => 19.69, 'min_price' => 1.5, 'max_price' => 40],
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
    public function it_computes_the_same_totals_through_multi_search_for_a_long_filter(): void
    {
        $codes = ['V-a', 'V-b', 'V-c', ...array_map(static fn (int $index): string => sprintf('V-%040d', $index), range(1, 97))];

        $response = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'filters' => [['field' => 'code', 'operator' => 'in', 'value' => $codes]], 'totals' => ['measures' => [['type' => 'sum', 'field' => 'number']], 'groups' => [['field' => 'active']]]]);

        $response->assertOk()->assertJsonPath('total', 3);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 3, 'sum_number' => 6],
            'groups' => [['field' => 'active', 'buckets' => [['value' => true, 'count' => 3]], 'other' => ['count' => 0], 'truncated' => false]],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_rounds_decimal_measures_to_the_scale_of_the_cast_as_sql_computes_them(): void
    {
        foreach ([1 => 19.99, 2 => 0.10, 3 => 1234.56, 5 => 19.99] as $id => $price) {
            CentsVoucher::query()->whereKey($id)->update(['price' => $price]);
        }

        CentsVoucher::query()->get()->searchable();
        $rows = CentsVoucher::query()->where('team', 'red')->get();

        $response = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price'], ['type' => 'avg', 'field' => 'price'], ['type' => 'min', 'field' => 'price'], ['type' => 'max', 'field' => 'price']]]]);

        $response->assertOk();
        $this->assertSame(['1274.64', '318.66', '0.10', '1234.56'], [(string) $rows->sum('price'), number_format((float) $rows->avg('price'), 2, '.', ''), (string) $rows->min('price'), (string) $rows->max('price')]);
        $this->assertSame(['count' => 4, 'sum_price' => 1274.64, 'avg_price' => 318.66, 'min_price' => 0.1, 'max_price' => 1234.56], $response->json('totals.all'));
    }

    #[Test]
    public function it_returns_empty_totals_without_a_hit(): void
    {
        $response = $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'filters' => [['field' => 'number', 'value' => 99]], 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price']], 'groups' => [['field' => 'active']]]]);

        $response->assertOk()->assertJsonPath('total', 0)->assertJsonPath('totals', ['exact' => true, 'all' => ['count' => 0, 'sum_price' => 0], 'groups' => [['field' => 'active', 'buckets' => [], 'other' => ['count' => 0], 'truncated' => false]]]);
    }

    #[Test]
    public function it_refuses_a_multi_valued_or_unfaceted_attribute_from_the_schema_even_in_a_cut_group(): void
    {
        Route::prefix('api')->group(static function (): void {
            Rest::resource('tagged-vouchers', TaggedTotalsVoucherController::class)->register();
        });
        $this->withoutExceptionHandling();
        $refusals = [];

        foreach (['tags', 'code'] as $attribute) {
            try {
                $this->search('tagged-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => $attribute, 'limit' => 1]]]]);
            } catch (EngineSearchException $exception) {
                $refusals[] = $exception->getMessage();
            }
        }

        $this->assertSame([
            EngineSearchException::multiValuedFacet(TaggedTotalsVoucherResource::class, 'tags')->getMessage(),
            EngineSearchException::missingFacet(TaggedTotalsVoucherResource::class, 'code', TypesenseEngine::class)->getMessage(),
        ], $refusals);
    }

    #[Test]
    public function it_names_an_attribute_that_the_collection_does_not_facet_on_both_endpoints(): void
    {
        $codes = array_map(static fn (int $index): string => sprintf('V-%040d', $index), range(1, 100));
        $this->withoutExceptionHandling();
        $expected = EngineSearchException::missingFacet(PerimeterTotalledVoucherResource::class, 'code', TypesenseEngine::class)->getMessage();

        foreach ([[], [['field' => 'code', 'operator' => 'in', 'value' => $codes]]] as $filters) {
            try {
                $this->search('perimeter-totalled-vouchers', ['source' => 'engine', 'filters' => $filters, 'totals' => ['groups' => [['field' => 'code']]]]);
                $this->fail('The search passed.');
            } catch (EngineSearchException $exception) {
                $this->assertSame($expected, $exception->getMessage());
            }
        }
    }
}

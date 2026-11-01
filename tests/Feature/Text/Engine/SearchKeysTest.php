<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Ledger\NumberedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\ScopedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineKeys;
use Lomkit\Rest\Text\Engine\ScoutModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchKeysTest extends SearchKeysTestCase
{
    private const array ENGINE_KEYS = ['V-f', 'V-e', 'V-d', 'V-c', 'V-a', 'V-zz'];

    /**
     * @param  class-string<Resource>  $resource
     * @param  array<string, mixed>  $parameters
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('pages')]
    public function it_loads_the_rows_of_the_engine_keys_inside_the_perimeter_in_key_order(string $resource, array $parameters, array $ids): void
    {
        $vouchers = $this->builder($resource)
            ->searchKeys(self::ENGINE_KEYS, $parameters)
            ->get();

        $this->assertSame($ids, $vouchers->modelKeys());
    }

    /** @return array<string, array{class-string<Resource>, array<string, mixed>, list<int>}> */
    public static function pages(): array
    {
        return [
            'the rows kept by the perimeter and soft deletes' => [VoucherResource::class, [], [5, 1, 3]],
            'the rows kept by the global scope of the model' => [ScopedVoucherResource::class, [], [1, 3]],
            'the trashed rows added' => [VoucherResource::class, ['trashed' => 'with'], [6, 5, 1, 3]],
            'the trashed rows added under the global scope' => [ScopedVoucherResource::class, ['trashed' => 'with'], [6, 1, 3]],
            'the trashed rows only' => [VoucherResource::class, ['trashed' => 'only'], [6]],
        ];
    }

    /**
     * @param  list<string>  $keys
     * @param  list<int>  $numbers
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('integerKeys')]
    public function it_loads_integer_scout_keys_given_as_strings_in_key_order(array $keys, array $numbers, array $ids): void
    {
        $vouchers = $this->builder(NumberedVoucherResource::class)
            ->searchKeys($keys)
            ->get();

        $this->assertSame($numbers, $vouchers->map(fn (Model $voucher): mixed => $voucher->getAttribute('number'))->all());
        $this->assertSame($ids, $vouchers->modelKeys());
    }

    /** @return array<string, array{list<string>, list<int>, list<int>}> */
    public static function integerKeys(): array
    {
        return [
            'the numbers in descending order' => [['3', '1'], [3, 1], [1, 3]],
            'the numbers in ascending order' => [['1', '3'], [1, 3], [3, 1]],
        ];
    }

    #[Test]
    public function it_drops_a_row_whose_scout_key_differs_from_the_engine_key(): void
    {
        $vouchers = $this->builder(VoucherResource::class)
            ->searchKeys(['v-c', 'V-a'])
            ->get();

        $this->assertSame([3], $vouchers->modelKeys());
    }

    #[Test]
    public function it_keeps_only_the_loaded_rows_of_the_keys_in_key_order(): void
    {
        $keys = EngineKeys::of(ScoutModel::tryFrom(new Voucher) ?? throw new LogicException('Voucher is indexed.'), ['V-a', 'V-zz', 'V-c']);

        $kept = $keys->keep(
            Voucher::query()
                ->whereKey([1, 2, 3])
                ->get(),
        );

        $this->assertSame([3, 1], $kept->modelKeys());
    }

    #[Test]
    public function it_loads_no_row_without_keys(): void
    {
        $vouchers = $this->builder(VoucherResource::class)
            ->searchKeys([])
            ->get();

        $this->assertSame([], $vouchers->modelKeys());
    }

    #[Test]
    public function it_loads_the_includes_and_aggregates_of_the_page_rows(): void
    {
        $query = $this->builder(VoucherResource::class)->searchKeys(['V-e', 'V-d', 'V-c', 'V-a'], [
            'includes' => [['relation' => 'lines']],
            'aggregates' => [['relation' => 'lines', 'type' => 'count', 'alias' => 'line_count']],
        ]);

        $this->assertSame(
            [[5, ['l5a'], 1], [1, ['l1b', 'l1a'], 2], [3, ['l3a'], 1]],
            $query
                ->get()
                ->map(fn (Model $voucher): array => [$voucher->getKey(), array_column($voucher->getRelation('lines')->all(), 'label'), (int) $voucher->getAttribute('line_count')])
                ->all(),
        );
    }

    #[Test]
    public function it_ignores_the_search_steps_that_ran_on_the_engine(): void
    {
        $query = $this->builder(VoucherResource::class)->searchKeys(['V-e', 'V-c', 'V-a'], [
            'text' => ['value' => 'alpha'],
            'filters' => [['field' => 'number', 'value' => 3]],
            'sorts' => [['field' => 'number', 'direction' => 'asc']],
            'limit' => 1,
            'page' => 2,
        ]);

        $this->assertSame([5, 1, 3], $query->get()->modelKeys());
    }
}

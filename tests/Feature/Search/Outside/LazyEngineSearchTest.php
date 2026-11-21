<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\EngineResultsChangedException;
use Lomkit\Rest\Exceptions\EngineWindowException;
use Lomkit\Rest\Exceptions\SearchRunException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CatchesLazyRefusals;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedRowsExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\BudgetEngineRestriction;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PagedTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RestrictedListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RestrictedListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ShiftingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\WalkedEngineProfiles;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class LazyEngineSearchTest extends EngineSearchTestCase
{
    use CatchesLazyRefusals;
    use ResetsExtensionStubs;

    private const array REPORT = ['text' => ['value' => 'report']];

    #[Test]
    #[DataProvider('drivers')]
    public function it_reads_every_row_of_a_text_search_over_engine_pages_in_engine_order_once_by_chunks(string $driver): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [WalkedEngineProfiles::class]]);
        $data = [...$this->pageData(1), ...$this->pageData(2)];
        WalkedEngineProfiles::$pages = [];

        $rows = Rest::search(VoucherResource::class)
            ->as(null)
            ->payload(self::REPORT)
            ->lazy(chunk: 3);

        $this->assertSame([[2, 1], [2, 2]], WalkedEngineProfiles::$pages);
        DB::enableQueryLog();
        $streamed = $rows->all();

        $this->assertSame([5, 4, 1, 3], array_column($streamed, 'id'));
        $this->assertSame(json_encode($data), json_encode($streamed));
        $this->assertSame([['V-e', 'V-d', 'V-c'], ['V-a']], $this->loadedKeys());
        $this->assertSame([[2, 1], [2, 2]], WalkedEngineProfiles::$pages);
    }

    #[Test]
    public function it_caps_the_rows_read_from_the_engine_with_max(): void
    {
        config(['scout.driver' => 'collection', 'rest.extensions' => [WalkedEngineProfiles::class]]);

        $rows = Rest::search(VoucherResource::class)
            ->as(null)
            ->payload(self::REPORT)
            ->lazy(max: 3);

        $this->assertSame([5, 4, 1], $rows->pluck('id')->all());
        $this->assertSame([[2, 1], [2, 2]], WalkedEngineProfiles::$pages);
    }

    #[Test]
    public function it_skips_a_row_deleted_since_its_key_was_read(): void
    {
        config(['scout.driver' => 'collection']);

        $rows = Rest::search(VoucherResource::class)
            ->as(null)
            ->payload(self::REPORT)
            ->lazy(chunk: 2);
        DB::table('vouchers')->where('id', 1)->delete();

        $this->assertSame([5, 4, 3], $rows->pluck('id')->all());
    }

    #[Test]
    public function it_reads_an_engine_list_through_the_perimeter_and_the_engine_restrictions_without_the_sql_perimeter(): void
    {
        config(['scout.driver' => 'collection', 'rest.extensions' => [BudgetEngineRestriction::class]]);
        Route::prefix('api')->group(fn () => Rest::resource('restricted-listed-vouchers', RestrictedListedVoucherController::class)->register());
        $search = Rest::search(RestrictedListedVoucherResource::class)
            ->as(new GenericUser(['id' => 1]))
            ->payload(['source' => 'engine']);
        $data = $this->search('restricted-listed-vouchers', ['source' => 'engine'])->assertOk()->json('data');

        $streamed = $search->lazy(chunk: 2)->all();

        $this->assertSame([4, 1, 3], array_column($streamed, 'id'));
        $this->assertSame([4, 1, 3], array_map(fn (Model $row): mixed => $row->getKey(), $search->page()->items()));
        $this->assertSame(json_encode($data), json_encode($streamed));
    }

    /** @param list<int> $ids */
    #[Test]
    #[DataProvider('windows')]
    public function it_reads_an_engine_list_within_the_window_of_the_engine(?int $max, array $ids): void
    {
        config(['scout.driver' => 'collection', 'rest.extensions' => [WalkedEngineProfiles::class]]);
        WalkedEngineProfiles::$window = 4;

        $rows = Rest::search(ListedVoucherResource::class)
            ->as(null)
            ->payload(['source' => 'engine'])
            ->lazy(max: $max);

        $this->assertSame($ids, $rows->pluck('id')->all());
    }

    /** @return array<string, array{?int, list<int>}> */
    public static function windows(): array
    {
        return [
            'a max at the window' => [4, [5, 4, 1, 2]],
            'a max under the window' => [1, [5]],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('refusals')]
    public function it_refuses_when_lazy_is_called_a_search_that_the_engine_cannot_return_whole(?int $window, ?int $reachable, array $search, ?int $max, ?int $named): void
    {
        config(['scout.driver' => 'collection', 'rest.extensions' => [WalkedEngineProfiles::class]]);
        WalkedEngineProfiles::$window = $window;
        WalkedEngineProfiles::$reachable = $reachable;

        $refusal = $this->lazyRefusal(fn () => Rest::search(ListedVoucherResource::class)
            ->as(null)
            ->payload($search)
            ->lazy(max: $max));

        $this->assertInstanceOf(EngineWindowException::class, $refusal);
        $this->assertSame($named, $refusal->window);
        $this->assertSame(SearchRunException::engineWindow(ListedVoucherResource::class, $named)->getMessage(), $refusal->getMessage());
    }

    /** @return array<string, array{?int, ?int, array<string, mixed>, ?int, ?int}> */
    public static function refusals(): array
    {
        return [
            'a list beyond the window' => [4, PHP_INT_MAX, ['source' => 'engine'], null, 4],
            'a list beyond the window with a max beyond it' => [4, PHP_INT_MAX, ['source' => 'engine'], 5, 4],
            'a text beyond the reach of the engine' => [null, 4, self::REPORT, null, 4],
            'a list beyond the reach of the engine' => [null, 3, ['source' => 'engine'], null, 3],
            'a list beyond the reach of the engine with a max beyond it' => [null, 3, ['source' => 'engine'], 4, 3],
            'an unknown reach' => [null, null, ['source' => 'engine'], null, null],
        ];
    }

    #[Test]
    public function it_reads_an_unknown_reach_up_to_the_max(): void
    {
        config(['scout.driver' => 'collection', 'rest.extensions' => [WalkedEngineProfiles::class]]);
        WalkedEngineProfiles::$reachable = null;

        $rows = Rest::search(ListedVoucherResource::class)
            ->as(null)
            ->payload(['source' => 'engine'])
            ->lazy(max: 3);

        $this->assertSame([5, 4, 1], $rows->pluck('id')->all());
    }

    #[Test]
    #[DataProvider('shifts')]
    public function it_refuses_when_lazy_is_called_engine_pages_that_change_while_their_keys_are_read(bool $repeats): void
    {
        $this->useEngine(new ShiftingCollectionEngine($repeats));
        config(['rest.extensions' => [WalkedEngineProfiles::class]]);

        $refusal = $this->lazyRefusal(fn () => Rest::search(VoucherResource::class)
            ->as(null)
            ->payload(self::REPORT)
            ->lazy());

        $this->assertInstanceOf(EngineResultsChangedException::class, $refusal);
        $this->assertSame('The search engine results of the resource ['.VoucherResource::class.'] changed while lazy() read their keys. Call lazy() again, and set scout.after_commit to true so that the index changes once the database transactions commit.', $refusal->getMessage());
    }

    /** @return array<string, array{bool}> */
    public static function shifts(): array
    {
        return [
            'a second page repeating a key' => [true],
            'a second page missing a key' => [false],
        ];
    }

    #[Test]
    public function it_reads_an_engine_without_exact_total_until_a_short_page(): void
    {
        $engine = new PagedTurbopufferEngine;
        $this->useEngine($engine);

        $rows = Rest::search(UnsortedVoucherResource::class)
            ->as(null)
            ->payload(self::REPORT)
            ->lazy();

        $this->assertSame([5, 4, 1, 3], $rows->pluck('id')->all());
        $this->assertSame([[1000, 1]], $engine->pages);
    }

    #[Test]
    public function it_names_the_result_window_of_an_engine_without_exact_total(): void
    {
        $this->useEngine(new PagedTurbopufferEngine(endless: true));

        $refusal = $this->lazyRefusal(fn () => Rest::search(UnsortedVoucherResource::class)
            ->as(null)
            ->payload(self::REPORT)
            ->lazy());

        $this->assertInstanceOf(EngineWindowException::class, $refusal);
        $this->assertSame(10000, $refusal->window);
        $this->assertSame('The search of the resource ['.UnsortedVoucherResource::class.'] runs on a search engine, which returns at most its first 10000 results, and the search can match more. Give lazy() a max of at most 10000, narrow the search, or read the pages with page().', $refusal->getMessage());
    }

    #[Test]
    public function it_reads_an_engine_search_with_its_instructions_and_trashed_rows(): void
    {
        config(['scout.driver' => 'collection', 'scout.soft_delete' => true]);
        $search = [...self::REPORT, 'trashed' => 'with', 'instructions' => [['name' => 'priced-up-to', 'fields' => [['name' => 'max', 'value' => 10]]]]];
        $data = $this->search('vouchers', $search)->assertOk()->json('data');

        $rows = Rest::search(VoucherResource::class)
            ->as(null)
            ->payload($search)
            ->lazy(chunk: 2);

        $this->assertSame([6, 1, 3], $rows->pluck('id')->all());
        $this->assertSame(json_encode($data), json_encode($rows->all()));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_reads_an_engine_search_through_the_row_restrictions_of_a_profile_that_applies_them(string $driver): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [RedRowsExtension::class]]);
        $data = $this->search('vouchers', self::REPORT)->assertOk()->json('data');

        $rows = Rest::search(VoucherResource::class)
            ->as(null)
            ->payload(self::REPORT)
            ->lazy(chunk: 2);

        $this->assertSame([5, 1, 3], $rows->pluck('id')->all());
        $this->assertSame(json_encode($data), json_encode($rows->all()));
    }

    #[Test]
    public function it_reads_the_rows_of_a_search_without_text_from_the_database(): void
    {
        config(['scout.driver' => 'collection']);
        $data = $this->search('vouchers', ['sorts' => [['field' => 'number']]])->assertOk()->json('data');

        $rows = Rest::search(VoucherResource::class)
            ->as(null)
            ->payload(['sorts' => [['field' => 'number']]])
            ->lazy(chunk: 2);

        $this->assertSame([3, 2, 1, 5], $rows->pluck('id')->all());
        $this->assertSame(json_encode($data), json_encode($rows->all()));
    }

    /** @return list<array<string, mixed>> */
    private function pageData(int $page): array
    {
        return $this->search('vouchers', [...self::REPORT, 'limit' => 2, 'page' => $page])->assertOk()->json('data');
    }

    /** @return list<list<string>> */
    private function loadedKeys(): array
    {
        $loads = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains(str_replace(['"', '`'], '', $query['query']), 'vouchers.code in'));

        return array_values(array_map(fn (array $query): array => $query['bindings'], $loads));
    }
}

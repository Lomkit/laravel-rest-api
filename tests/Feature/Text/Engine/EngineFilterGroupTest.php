<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CheapEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\CapturingMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\GroupedReaderVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedTeamVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineFilterGroupTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    private const array EITHER = [['field' => 'number', 'value' => 3], ['field' => 'number', 'value' => 4, 'type' => 'or']];

    private const array NESTED = [['field' => 'active', 'value' => true], ['nested' => [['field' => 'number', 'value' => 3], ['field' => 'price', 'operator' => '>', 'value' => 20, 'type' => 'or']]]];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(function (): void {
            Rest::resource('listed-vouchers', ListedVoucherController::class)->register();
            Rest::resource('listed-team-vouchers', ListedTeamVoucherController::class)->register();
            Rest::resource('grouped-reader-vouchers', GroupedReaderVoucherController::class)->register();
        });
        config(['scout.typesense.model-settings.'.ReaderVoucher::class.'.search-parameters.query_by' => 'title']);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('filterGroups')]
    public function it_filters_a_list_and_a_text_search_with_or_and_nested_groups(string $driver, array $search, array $ids): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('listed-vouchers', $search);

        $response->assertOk()->assertJsonPath('data.*.id', $ids)->assertJsonPath('total', count($ids));
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<int>}> */
    public static function filterGroups(): iterable
    {
        foreach (self::drivers() as $engine => [$driver]) {
            yield "an or of two conditions in a list on {$engine}" => [$driver, ['filters' => self::EITHER], [1, 4]];
            yield "a nested group in a list on {$engine}" => [$driver, ['filters' => self::NESTED], [2, 1]];
            yield "an or of two conditions in a text search on {$engine}" => [$driver, ['text' => ['value' => 'report'], 'filters' => self::EITHER, 'sorts' => [['field' => 'code']]], [1, 4]];
        }
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_never_returns_a_row_that_the_perimeter_or_a_restriction_excludes_through_an_or(string $driver): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [CheapEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));

        $response = $this->search('listed-team-vouchers', ['filters' => [['field' => 'number', 'value' => 5], ['field' => 'number', 'value' => 3, 'type' => 'or'], ['field' => 'number', 'value' => 4, 'type' => 'or']]]);

        $response->assertOk()->assertExactJson($this->page([1], 1));
    }

    /** @param list<array<string, mixed>> $filters */
    #[Test]
    #[DataProvider('sqlFilters')]
    public function it_writes_the_client_block_beside_the_flat_conditions_in_sql(string $driver, array $filters, string $sql): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [CheapEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
        DB::enableQueryLog();

        $this->search('listed-team-vouchers', ['filters' => $filters])->assertOk();

        $this->assertSame($sql, str_replace(['"', '`'], '', (string) (Arr::first(DB::getQueryLog(), fn (array $query): bool => str_contains((string) $query['query'], 'number'))['query'] ?? '')));
    }

    /** @return array<string, array{string, list<array<string, mixed>>, string}> */
    public static function sqlFilters(): array
    {
        return [
            'an or on the database engine' => ['database', self::EITHER, 'select count(*) as aggregate from vouchers where price < ? and team in (?) and (number = ? or number = ?) and vouchers.deleted_at is null'],
            'a nested group on the database engine' => ['database', self::NESTED, 'select count(*) as aggregate from vouchers where price < ? and team in (?) and (active = ? and (number = ? or price > ?)) and vouchers.deleted_at is null'],
            'an or on the collection engine' => ['collection', self::EITHER, 'select * from vouchers where price < ? and team in (?) and (number = ? or number = ?) and vouchers.deleted_at is null order by code asc'],
            'a nested group on the collection engine' => ['collection', self::NESTED, 'select * from vouchers where price < ? and team in (?) and (active = ? and (number = ? or price > ?)) and vouchers.deleted_at is null order by code asc'],
        ];
    }

    #[Test]
    public function it_writes_the_whole_filter_of_typesense(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, CheapEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('grouped-reader-vouchers', ['filters' => self::EITHER])->assertOk();
        $this->search('grouped-reader-vouchers', ['filters' => self::NESTED])->assertOk();

        $this->assertSame([
            '(price:<35 && team:=[`red`] && readers:=[`ann`]) && (number:=3 || number:=4)',
            '(price:<35 && team:=[`red`] && readers:=[`ann`]) && (active:=true && (number:=3 || price:>20))',
        ], $engine->filters);
        $this->assertSame('*', $engine->parameters[0]['q']);
    }

    #[Test]
    public function it_writes_the_whole_filter_of_meilisearch(): void
    {
        $engine = new CapturingMeilisearchEngine(new SettingsClient(fn (): array => ['maxTotalHits' => 1000]));
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, CheapEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('grouped-reader-vouchers', ['filters' => self::EITHER])->assertOk();
        $this->search('grouped-reader-vouchers', ['filters' => self::NESTED])->assertOk();

        $this->assertSame([
            '(price<35 AND team IN ["red"] AND readers IN ["ann"]) AND (number=3 OR number=4)',
            '(price<35 AND team IN ["red"] AND readers IN ["ann"]) AND (active=true AND (number=3 OR price>20))',
        ], array_column($engine->requests, 'filter'));
    }

    #[Test]
    public function it_accepts_groups_on_the_null_engine_and_answers_an_empty_page(): void
    {
        $this->useEngine(new NullEngine);

        $response = $this->search('listed-vouchers', ['filters' => self::NESTED]);

        $response->assertOk()->assertExactJson($this->page([], 0));
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('bounds')]
    public function it_bounds_the_depth_and_the_count_of_the_groups_as_the_database_filters(array $filters, array $errors): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);
        config(['rest.limits.totals.filters' => 5]);

        $response = $this->search('listed-vouchers', ['filters' => $filters]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame([], $engine->searches);
    }

    /** @return array<string, array{list<array<string, mixed>>, array<string, list<string>>}> */
    public static function bounds(): array
    {
        return [
            'a group inside a group' => [[['nested' => [['nested' => [['field' => 'number', 'value' => 3]]]]]], [
                'search.filters.0.nested.0.nested' => ['The search.filters.0.nested.0.nested field is prohibited.'],
            ]],
            'more filters in the search than the limit' => [[['field' => 'number', 'value' => 3], ['field' => 'number', 'value' => 4, 'type' => 'or'], ['nested' => array_fill(0, 4, ['field' => 'number', 'value' => 3])]], [
                'search.filters.2.nested' => ['The search.filters.2.nested field goes over the limit of 5 filters in one search.'],
            ]],
        ];
    }
}

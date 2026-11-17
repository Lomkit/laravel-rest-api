<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CheapEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EitherEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EmptyGroupEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OpenGroupEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderGroupEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\CapturingMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\GroupedReaderVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\GroupedReaderVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedTeamVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedUnsortedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedUnsortedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineRestrictionGroupTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    private const array EITHER = [['field' => 'number', 'value' => 3], ['field' => 'number', 'value' => 4, 'type' => 'or']];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(function (): void {
            Rest::resource('listed-team-vouchers', ListedTeamVoucherController::class)->register();
            Rest::resource('grouped-reader-vouchers', GroupedReaderVoucherController::class)->register();
            Rest::resource('listed-unsorted-vouchers', ListedUnsortedVoucherController::class)->register();
        });
        config(['scout.typesense.model-settings.'.ReaderVoucher::class.'.search-parameters.query_by' => 'title']);
    }

    #[Test]
    public function it_writes_the_extension_group_into_the_whole_filter_of_typesense(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, CheapEngineRestriction::class, ReaderGroupEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('grouped-reader-vouchers', ['source' => 'engine'])->assertOk();
        $this->search('grouped-reader-vouchers', ['source' => 'engine', 'filters' => self::EITHER])->assertOk();

        $this->assertSame([
            '(price:<35 && team:=[`red`] && readers:=[`ann`]) && (number:=1 || (price:<5 && readers:=[`bob`] && readers:!=[`cid`]))',
            '(price:<35 && team:=[`red`] && readers:=[`ann`]) && ((number:=1 || (price:<5 && readers:=[`bob`] && readers:!=[`cid`])) && (number:=3 || number:=4))',
        ], $engine->filters);
    }

    #[Test]
    public function it_writes_the_extension_group_into_the_whole_filter_of_meilisearch(): void
    {
        $engine = new CapturingMeilisearchEngine(new SettingsClient(fn (): array => ['maxTotalHits' => 1000]));
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, CheapEngineRestriction::class, ReaderGroupEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('grouped-reader-vouchers', ['source' => 'engine'])->assertOk();
        $this->search('grouped-reader-vouchers', ['source' => 'engine', 'filters' => self::EITHER])->assertOk();

        $this->assertSame([
            '(price<35 AND team IN ["red"] AND readers IN ["ann"]) AND (number=1 OR (price<5 AND readers IN ["bob"] AND readers NOT IN ["cid"]))',
            '(price<35 AND team IN ["red"] AND readers IN ["ann"]) AND ((number=1 OR (price<5 AND readers IN ["bob"] AND readers NOT IN ["cid"])) AND (number=3 OR number=4))',
        ], array_column($engine->requests, 'filter'));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_drops_the_whole_condition_of_an_extension_whose_or_group_holds_a_true_condition(string $driver): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [OpenGroupEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));

        $response = $this->search('listed-team-vouchers', ['source' => 'engine']);

        $response->assertOk()->assertExactJson($this->page([5, 1, 2, 3], 4));
    }

    #[Test]
    public function it_ands_the_groups_of_two_extensions(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, CheapEngineRestriction::class, ReaderGroupEngineRestriction::class, EitherEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('grouped-reader-vouchers', ['source' => 'engine'])->assertOk();

        $this->assertSame(['(price:<35 && team:=[`red`] && readers:=[`ann`]) && ((number:=5 || number:=[4] || (active:=true && price:>20)) && (number:=1 || (price:<5 && readers:=[`bob`] && readers:!=[`cid`])))'], $engine->filters);
    }

    /** @param list<array<string, mixed>> $filters */
    #[Test]
    #[DataProvider('sqlGroups')]
    public function it_writes_the_extension_group_beside_the_flat_conditions_in_sql(string $driver, array $filters, string $sql): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [CheapEngineRestriction::class, EitherEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
        DB::enableQueryLog();

        $this->search('listed-team-vouchers', ['source' => 'engine', 'filters' => $filters])->assertOk();

        $this->assertSame($sql, str_replace(['"', '`'], '', (string) (Arr::first(DB::getQueryLog(), fn (array $query): bool => str_contains((string) $query['query'], 'number'))['query'] ?? '')));
    }

    /** @return array<string, array{string, list<array<string, mixed>>, string}> */
    public static function sqlGroups(): array
    {
        return [
            'a group on the database engine' => ['database', [], 'select count(*) as aggregate from vouchers where price < ? and team in (?) and (number = ? or number in (?) or (active = ? and price > ?)) and vouchers.deleted_at is null'],
            'a group and a client or on the database engine' => ['database', self::EITHER, 'select count(*) as aggregate from vouchers where price < ? and team in (?) and ((number = ? or number in (?) or (active = ? and price > ?)) and (number = ? or number = ?)) and vouchers.deleted_at is null'],
            'a group on the collection engine' => ['collection', [], 'select * from vouchers where price < ? and team in (?) and (number = ? or number in (?) or (active = ? and price > ?)) and vouchers.deleted_at is null order by code desc'],
            'a group and a client or on the collection engine' => ['collection', self::EITHER, 'select * from vouchers where price < ? and team in (?) and ((number = ? or number in (?) or (active = ? and price > ?)) and (number = ? or number = ?)) and vouchers.deleted_at is null order by code desc'],
        ];
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_never_returns_a_row_that_the_perimeter_or_another_restriction_excludes_through_a_group(string $driver): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [EitherEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
        $grouped = $this->search('listed-team-vouchers', ['source' => 'engine']);
        config(['rest.extensions' => [CheapEngineRestriction::class, EitherEngineRestriction::class]]);

        $restricted = $this->search('listed-team-vouchers', ['source' => 'engine']);
        $widened = $this->search('listed-team-vouchers', ['source' => 'engine', 'filters' => [['field' => 'number', 'value' => 5], ['field' => 'number', 'value' => 4, 'type' => 'or'], ['field' => 'number', 'value' => 2, 'type' => 'or']]]);

        $grouped->assertOk()->assertExactJson($this->page([5, 2], 2));
        $restricted->assertOk()->assertExactJson($this->page([2], 1));
        $widened->assertOk()->assertExactJson($this->page([2], 1));
    }

    #[Test]
    public function it_matches_nothing_for_a_group_without_a_condition_and_calls_no_engine(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [EmptyGroupEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));

        $list = $this->search('listed-team-vouchers', ['source' => 'engine']);
        $text = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $list->assertOk()->assertExactJson($this->page([], 0));
        $text->assertOk()->assertExactJson($this->page([], 0));
        $this->assertSame([], $engine->searches);
    }

    #[Test]
    public function it_names_the_extension_of_a_list_in_a_group_on_an_engine_that_does_not_match_lists(): void
    {
        config(['rest.extensions' => [ReadersExtension::class, ReaderGroupEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineExtensionException::refusedEngineCondition('reader_group', ReaderGroupEngineRestriction::class, GroupedReaderVoucherResource::class, InvalidEngineQueryException::unmatchedStringList(GroupedReaderVoucherResource::class, 'readers', CollectionEngine::class)));

        $this->search('grouped-reader-vouchers', ['source' => 'engine']);
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('ungroupedEngines')]
    public function it_refuses_a_group_on_an_engine_that_does_not_accept_groups(string $engine, array $extensions, string $route, string $resource): void
    {
        $this->useEngine(self::engine($engine));
        config(['rest.extensions' => [...$extensions, EmptyGroupEngineRestriction::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineExtensionException::ungroupedRestriction('empty_group', EmptyGroupEngineRestriction::class, $resource, $engine));

        $this->search($route, ['text' => ['value' => 'report']]);
    }

    /** @return array<string, array{class-string, list<class-string>, string, class-string}> */
    public static function ungroupedEngines(): array
    {
        return [
            'algolia' => [FakeAlgolia4Engine::class, [], 'listed-unsorted-vouchers', ListedUnsortedVoucherResource::class],
            'a provided profile' => [ForeignEngine::class, [ForeignEngineProfiles::class], 'vouchers', VoucherResource::class],
        ];
    }

    /**
     * @param  list<class-string>  $extensions
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('groupFlags')]
    public function it_tells_the_extension_whether_the_engine_accepts_groups(string $engine, array $extensions, string $route, bool $groups, array $ids): void
    {
        $this->useEngine(self::engine($engine));
        config(['rest.extensions' => [...$extensions, EitherEngineRestriction::class]]);

        $response = $this->search($route, ['text' => ['value' => 'report'], 'limit' => 10]);

        $response->assertOk()->assertJsonPath('data.*.id', $ids);
        $context = EngineLog::$contexts[array_key_last(EngineLog::$contexts)];
        $this->assertInstanceOf(EngineRestrictionContext::class, $context);
        $this->assertSame($groups, $context->filtersWithGroups);
    }

    /** @return array<string, array{class-string, list<class-string>, string, bool, list<int>}> */
    public static function groupFlags(): array
    {
        return [
            'the collection engine' => [RecordingCollectionEngine::class, [], 'vouchers', true, [5, 4]],
            'the database engine' => [DatabaseEngine::class, [], 'vouchers', true, [5, 4]],
            'the null engine' => [NullEngine::class, [], 'vouchers', true, []],
            'algolia' => [FakeAlgolia4Engine::class, [], 'listed-unsorted-vouchers', false, []],
            'a provided profile' => [ForeignEngine::class, [ForeignEngineProfiles::class], 'vouchers', false, []],
        ];
    }

    private static function engine(string $class): object
    {
        return match ($class) {
            ForeignEngine::class => new ForeignEngine,
            FakeAlgolia4Engine::class => new FakeAlgolia4Engine(new SettingsClient(fn (): array => [])),
            DatabaseEngine::class => new DatabaseEngine,
            NullEngine::class => new NullEngine,
            default => new RecordingCollectionEngine,
        };
    }
}

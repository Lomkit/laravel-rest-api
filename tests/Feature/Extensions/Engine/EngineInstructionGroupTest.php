<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CheapEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EitherEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EmptyGroupInstruction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\GroupInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderGroupEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\CapturingMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\GroupedReaderVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListCapturingMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedTeamVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedUnsortedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedUnsortedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PerimeterGroupReaderVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PerimeterGroupReaderVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineInstructionGroupTest extends EngineSearchTestCase
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
            Rest::resource('reader-vouchers', ReaderVoucherController::class)->register();
            Rest::resource('perimeter-group-reader-vouchers', PerimeterGroupReaderVoucherController::class)->register();
        });
        config(['scout.typesense.model-settings.'.ReaderVoucher::class.'.search-parameters.query_by' => 'title']);
    }

    #[Test]
    public function it_writes_the_instruction_group_into_the_whole_filter_of_typesense(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, CheapEngineRestriction::class, GroupInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('grouped-reader-vouchers', ['source' => 'engine', 'instructions' => [['name' => 'gi.reader-group']]])->assertOk();
        $this->search('grouped-reader-vouchers', ['source' => 'engine', 'filters' => self::EITHER, 'instructions' => [['name' => 'gi.reader-group']]])->assertOk();

        $this->assertSame([
            '(price:<35 && team:=[`red`] && readers:=[`ann`]) && (number:=1 || (price:<5 && readers:=[`bob`] && readers:!=[`cid`]))',
            '(price:<35 && team:=[`red`] && readers:=[`ann`]) && ((number:=1 || (price:<5 && readers:=[`bob`] && readers:!=[`cid`])) && (number:=3 || number:=4))',
        ], $engine->filters);
    }

    #[Test]
    public function it_writes_the_instruction_group_into_the_whole_filter_of_meilisearch(): void
    {
        $engine = new CapturingMeilisearchEngine(new SettingsClient(fn (): array => ['maxTotalHits' => 1000]));
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, CheapEngineRestriction::class, GroupInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('grouped-reader-vouchers', ['source' => 'engine', 'instructions' => [['name' => 'gi.reader-group']]])->assertOk();
        $this->search('grouped-reader-vouchers', ['source' => 'engine', 'filters' => self::EITHER, 'instructions' => [['name' => 'gi.reader-group']]])->assertOk();

        $this->assertSame([
            '(price<35 AND team IN ["red"] AND readers IN ["ann"]) AND (number=1 OR (price<5 AND readers IN ["bob"] AND readers NOT IN ["cid"]))',
            '(price<35 AND team IN ["red"] AND readers IN ["ann"]) AND ((number=1 OR (price<5 AND readers IN ["bob"] AND readers NOT IN ["cid"])) AND (number=3 OR number=4))',
        ], array_column($engine->requests, 'filter'));
    }

    #[Test]
    public function it_ands_the_groups_of_an_extension_and_an_instruction(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, EitherEngineRestriction::class, GroupInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('grouped-reader-vouchers', ['source' => 'engine', 'instructions' => [['name' => 'gi.reader-group']]])->assertOk();

        $this->assertSame(['(team:=[`red`] && readers:=[`ann`]) && ((number:=5 || number:=[4] || (active:=true && price:>20)) && (number:=1 || (price:<5 && readers:=[`bob`] && readers:!=[`cid`])))'], $engine->filters);
    }

    /** @param list<array<string, mixed>> $filters */
    #[Test]
    #[DataProvider('sqlGroups')]
    public function it_writes_the_instruction_group_beside_the_flat_conditions_in_sql(string $driver, array $filters, string $sql): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [CheapEngineRestriction::class, GroupInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
        DB::enableQueryLog();

        $this->search('listed-team-vouchers', ['source' => 'engine', 'filters' => $filters, 'instructions' => [['name' => 'gi.either']]])->assertOk();

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
    public function it_never_returns_a_row_that_the_perimeter_or_a_restriction_excludes_through_an_instruction_group(string $driver): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [GroupInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
        $grouped = $this->search('listed-team-vouchers', ['source' => 'engine', 'instructions' => [['name' => 'gi.either']]]);
        config(['rest.extensions' => [CheapEngineRestriction::class, GroupInstructionsExtension::class]]);

        $restricted = $this->search('listed-team-vouchers', ['source' => 'engine', 'instructions' => [['name' => 'gi.either']]]);
        $widened = $this->search('listed-team-vouchers', ['source' => 'engine', 'filters' => [['field' => 'number', 'value' => 5], ['field' => 'number', 'value' => 4, 'type' => 'or'], ['field' => 'number', 'value' => 2, 'type' => 'or']], 'instructions' => [['name' => 'gi.either']]]);

        $grouped->assertOk()->assertExactJson($this->page([5, 2], 2));
        $restricted->assertOk()->assertExactJson($this->page([2], 1));
        $widened->assertOk()->assertExactJson($this->page([2], 1));
    }

    #[Test]
    public function it_matches_nothing_for_an_instruction_group_without_a_condition_and_calls_no_engine(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [GroupInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));

        $list = $this->search('listed-team-vouchers', ['source' => 'engine', 'instructions' => [['name' => 'gi.nothing']]]);
        $text = $this->search('vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'gi.nothing']]]);

        $list->assertOk()->assertExactJson($this->page([], 0));
        $text->assertOk()->assertExactJson($this->page([], 0));
        $this->assertSame([], $engine->searches);
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('ungroupedEngines')]
    public function it_refuses_an_instruction_group_on_an_engine_that_does_not_accept_groups(string $engine, array $extensions, string $route, string $resource): void
    {
        $this->useEngine(self::engine($engine));
        config(['rest.extensions' => [...$extensions, GroupInstructionsExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectException(InvalidEngineQueryException::class);
        $this->expectExceptionMessage('The instruction [gi.nothing] '.EmptyGroupInstruction::class.' of '.$resource.' groups engine conditions with whereAny(), but the engine ['.$engine.'] does not accept grouped conditions');

        $this->search($route, ['text' => ['value' => 'report'], 'instructions' => [['name' => 'gi.nothing']]]);
    }

    /** @return array<string, array{class-string, list<class-string>, string, class-string}> */
    public static function ungroupedEngines(): array
    {
        return [
            'algolia' => [FakeAlgolia4Engine::class, [], 'listed-unsorted-vouchers', ListedUnsortedVoucherResource::class],
            'a provided profile' => [ForeignEngine::class, [ForeignEngineProfiles::class], 'vouchers', VoucherResource::class],
        ];
    }

    #[Test]
    public function it_names_the_instruction_the_resource_and_the_engine_of_a_refused_group(): void
    {
        $this->assertSame(
            'The instruction [nothing] '.EmptyGroupInstruction::class.' of '.VoucherResource::class.' groups engine conditions with whereAny(), but the engine ['.ForeignEngine::class.'] does not accept grouped conditions: only the built-in Typesense, Meilisearch, Turbopuffer, database, collection and null profiles do. Check $query->filtersWithGroups() before grouping, and use where(), whereIn() and whereNotIn() on this engine.',
            InvalidEngineQueryException::ungroupedConditions(new EmptyGroupInstruction, VoucherResource::class, ForeignEngine::class)->getMessage(),
        );
        $this->assertStringStartsWith('ScoutEngine::perimeter() of '.VoucherResource::class.' groups engine conditions with whereAny(),', InvalidEngineQueryException::ungroupedConditions(null, VoucherResource::class, ForeignEngine::class)->getMessage());
    }

    /**
     * @param  list<class-string>  $extensions
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('groupFlags')]
    public function it_tells_the_instruction_whether_the_engine_accepts_groups_as_it_tells_extensions(string $engine, array $extensions, string $route, bool $groups, array $ids): void
    {
        $this->useEngine(self::engine($engine));
        config(['rest.extensions' => [...$extensions, EitherEngineRestriction::class, GroupInstructionsExtension::class]]);

        $response = $this->search($route, ['text' => ['value' => 'report'], 'limit' => 10, 'instructions' => [['name' => 'gi.either']]]);

        $response->assertOk()->assertJsonPath('data.*.id', $ids);
        $context = EngineLog::$contexts[array_key_last(EngineLog::$contexts)];
        $this->assertInstanceOf(EngineRestrictionContext::class, $context);
        $this->assertSame($groups, $context->filtersWithGroups);
        $this->assertSame($groups ? 'instruction groups' : 'instruction without groups', EngineLog::$calls[array_key_last(EngineLog::$calls)] ?? null);
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

    /** @param list<string> $readers */
    #[Test]
    #[DataProvider('chosenReaders')]
    public function it_writes_a_group_of_values_that_the_client_sends_to_the_instruction(array $readers, string $typesense, string $meilisearch): void
    {
        config(['rest.extensions' => [ReadersExtension::class, GroupInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
        $search = ['text' => ['value' => 'report'], 'instructions' => [['name' => 'gi.chosen-readers', 'fields' => [['name' => 'readers', 'value' => $readers]]]]];
        $typesenseEngine = new FilterCapturingTypesenseEngine;
        $meilisearchEngine = new ListCapturingMeilisearchEngine(new SettingsClient(fn (): array => ['maxTotalHits' => 1000]));

        $this->useEngine($typesenseEngine);
        $this->search('reader-vouchers', $search)->assertOk();
        $this->useEngine($meilisearchEngine);
        $this->search('reader-vouchers', $search)->assertOk();

        $this->assertSame([$typesense], $typesenseEngine->filters);
        $this->assertSame([$meilisearch], $meilisearchEngine->filters);
    }

    /** @return array<string, array{list<string>, string, string}> */
    public static function chosenReaders(): array
    {
        return [
            'two readers' => [['bob', 'cid'], '(readers:=[`ann`]) && (readers:=[`bob`, `cid`] || number:=1)', '(readers IN ["ann"]) AND (readers IN ["bob", "cid"] OR number=1)'],
            'no reader, a false condition' => [[], '(readers:=[`ann`]) && (number:=1)', '(readers IN ["ann"]) AND (number=1)'],
        ];
    }

    #[Test]
    public function it_escapes_a_quote_that_the_client_sends_to_an_instruction_group_on_meilisearch(): void
    {
        $engine = new ListCapturingMeilisearchEngine(new SettingsClient(fn (): array => ['maxTotalHits' => 1000]));
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, GroupInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('reader-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'gi.chosen-readers', 'fields' => [['name' => 'readers', 'value' => ['b" OR readers IN ["cid']]]]]])->assertOk();

        $this->assertSame(['(readers IN ["ann"]) AND (readers IN ["b\" OR readers IN [\"cid"] OR number=1)'], $engine->filters);
    }

    #[Test]
    public function it_refuses_a_backtick_that_the_client_sends_to_an_instruction_group_on_typesense(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, GroupInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
        $this->withoutExceptionHandling();

        try {
            $this->search('reader-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'gi.chosen-readers', 'fields' => [['name' => 'readers', 'value' => ['b` || number:=5 || readers:=[`c']]]]]]);
            $this->fail('The backtick reached the filter.');
        } catch (InvalidEngineQueryException $refusal) {
            $this->assertEquals(InvalidEngineQueryException::nonLiteralValue('readers'), $refusal);
        }

        $this->assertSame([], $engine->filters);
    }

    #[Test]
    public function it_orders_the_root_of_the_filter_as_groups_then_anded_lists_then_the_client_block(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, ReaderGroupEngineRestriction::class, ReaderInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('reader-vouchers', ['text' => ['value' => 'report'], 'filters' => self::EITHER, 'instructions' => [['name' => 'ri.bob']]])->assertOk();

        $this->assertSame(['(readers:=[`ann`]) && ((number:=1 || (price:<5 && readers:=[`bob`] && readers:!=[`cid`])) && readers:=[`bob`] && (number:=3 || number:=4))'], $engine->filters);
    }

    #[Test]
    public function it_ands_a_perimeter_group_and_a_perimeter_list_with_an_extension_list(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('perimeter-group-reader-vouchers', ['text' => ['value' => 'report']])->assertOk();

        $this->assertSame(['(readers:=[`bob`]) && ((number:=1 || active:=false) && readers:=[`ann`])'], $engine->filters);
    }

    #[Test]
    public function it_refuses_a_perimeter_group_on_an_engine_that_does_not_accept_groups(): void
    {
        $this->useEngine(new FakeAlgolia4Engine(new SettingsClient(fn (): array => [])));
        config(['rest.extensions' => [ReadersExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineQueryException::ungroupedConditions(null, PerimeterGroupReaderVoucherResource::class, FakeAlgolia4Engine::class));

        $this->search('perimeter-group-reader-vouchers', ['text' => ['value' => 'report']]);
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Query\EngineRestrictions;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CheapEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ComparedPriceVoucherController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\GuestlessEngineExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\NumberEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OwnerEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PriceEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedTeamEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RowReplacingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SqlAwareEngineExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TeamIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\UnappliedEngineRestriction;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PagedMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainEngineVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainProfile;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnboundedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnboundedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineIndex;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\IndexAttributes;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

#[Group('scout')]
class EngineRestrictionTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
    }

    #[Test]
    public function it_restricts_an_engine_search_of_a_resource_without_perimeter(): void
    {
        config(['rest.extensions' => [TeamIndexExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 1, 3], 3));
        $this->assertSame(['team' => ['blue']], $this->builder()->whereNotIns);
    }

    #[Test]
    public function it_restricts_after_the_perimeter_and_before_the_client_filters(): void
    {
        config(['rest.extensions' => [RedTeamEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['blue', 'red']]));

        $response = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'number', 'operator' => 'in', 'value' => [1, 4]]]]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $this->assertSame(['team' => ['red'], 'number' => [1, 4]], $this->builder()->whereIns);
    }

    #[Test]
    public function it_applies_the_extension_between_the_perimeter_and_the_client(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('compared-price-vouchers', ComparedPriceVoucherController::class)->register());
        config(['rest.extensions' => [CheapEngineRestriction::class]]);

        $response = $this->search('compared-price-vouchers', [
            'text' => ['value' => 'report'],
            'filters' => [['field' => 'price', 'operator' => '>=', 'value' => 1]],
            'instructions' => [['name' => 'priced-up-to', 'fields' => [['name' => 'max', 'value' => 30]]]],
        ]);

        $response->assertOk()->assertExactJson($this->page([4, 1, 3], 3));
        $wheres = array_values(array_filter($this->builder()->wheres, fn (array $where): bool => $where['field'] !== '__soft_deleted'));
        $this->assertSame([['>', 0], ['<', 35], ['>=', 1], ['<', 30]], array_map(fn (array $where): array => [$where['operator'], $where['value']], $wheres));
    }

    /** @param list<class-string<Extension>> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_gives_the_same_conditions_and_rows_in_any_extension_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([4, 1, 3], 3));
        $builder = $this->builder();
        $this->assertSame(
            [
                [['field' => 'price', 'operator' => '<', 'value' => 20], ['field' => 'price', 'operator' => '>', 'value' => 1]],
                ['number' => [1, 3, 4, 5]],
                ['number' => [5]],
            ],
            [$builder->wheres, $builder->whereIns, $builder->whereNotIns],
        );
    }

    /** @return array<string, array{list<class-string<Extension>>}> */
    public static function orders(): array
    {
        return [
            'in name order' => [[NumberEngineRestriction::class, PriceEngineRestriction::class]],
            'in reverse order' => [[PriceEngineRestriction::class, NumberEngineRestriction::class]],
        ];
    }

    #[Test]
    public function it_keeps_a_value_an_extension_excludes_out_of_a_client_filter(): void
    {
        config(['rest.extensions' => [PriceEngineRestriction::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'number', 'operator' => 'in', 'value' => [4, 5]]]]);

        $response->assertOk()->assertExactJson($this->page([4], 1));
        $this->assertSame(['number' => [5]], $this->builder()->whereNotIns);
    }

    #[Test]
    public function it_narrows_a_client_in_filter_to_the_values_of_the_extension(): void
    {
        config(['rest.extensions' => [NumberEngineRestriction::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'number', 'operator' => 'in', 'value' => [2, 3]]]]);

        $response->assertOk()->assertExactJson($this->page([1], 1));
        $this->assertSame(['number' => [3]], $this->builder()->whereIns);
    }

    #[Test]
    public function it_returns_an_empty_page_without_calling_the_engine_for_a_guest(): void
    {
        config(['rest.extensions' => [GuestlessEngineExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([], 0));
        $this->assertSame([], $this->engine->searches);
        $this->assertSame([null], EngineLog::$users);
    }

    #[Test]
    public function it_runs_an_action_on_no_row_for_a_guest(): void
    {
        config(['rest.extensions' => [GuestlessEngineExtension::class]]);

        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->assertSame([], $this->engine->searches);
        $this->assertSame([5], Voucher::withTrashed()->where('active', false)->pluck('id')->all());
    }

    #[Test]
    public function it_runs_an_action_on_no_row_for_a_guest_when_the_engine_does_not_report_its_limit(): void
    {
        config(['rest.extensions' => [GuestlessEngineExtension::class]]);
        $this->useEngine(new PagedMeilisearchEngine(new SettingsClient(fn (): never => throw new RuntimeException('The key cannot read the settings.'))));

        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->assertSame([5], Voucher::withTrashed()->where('active', false)->pluck('id')->all());
    }

    #[Test]
    public function it_searches_for_the_user_of_the_request_that_the_extension_lets_through(): void
    {
        config(['rest.extensions' => [GuestlessEngineExtension::class]]);
        $user = new GenericUser(['id' => 3]);

        $response = $this->actingAs($user)->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 3], 4));
        $this->assertSame([$user], EngineLog::$users);
    }

    #[Test]
    public function it_gives_the_extension_the_gate_user_of_rest_search_as(): void
    {
        config(['rest.extensions' => [GuestlessEngineExtension::class]]);
        $user = new GenericUser(['id' => 7]);
        $checked = [];
        Gate::before(function (?Authenticatable $gateUser) use (&$checked): ?bool {
            $checked[] = $gateUser;

            return null;
        });

        $page = Rest::search(VoucherResource::class)->as($user)->payload(['text' => ['value' => 'report']])->page();

        $this->assertSame(4, $page->total());
        $this->assertSame([$user], EngineLog::$users);
        $this->assertNotSame([], $checked);
        $this->assertSame($user, $checked[0]);
    }

    #[Test]
    public function it_restricts_the_targets_of_an_action_once(): void
    {
        config(['rest.extensions' => [TeamIndexExtension::class]]);

        $response = $this->postJson('/api/vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertSame(['VoucherResource'], EngineLog::$calls);
        $this->assertSame([[2, 1], [2, 2]], $this->engine->pages);
        $this->assertSame(['Renamed V-c', 'Budget', 'Renamed V-a', 'Report blue', 'Renamed V-e', 'Report trashed'], Voucher::withTrashed()->orderBy('id')->pluck('title')->all());
    }

    #[Test]
    public function it_still_requires_the_index_perimeter_declaration(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('unbounded-vouchers', UnboundedVoucherController::class)->register());
        config(['rest.extensions' => [GuestlessEngineExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::missingPerimeter(UnboundedVoucherResource::class));

        $this->search('unbounded-vouchers', ['text' => ['value' => 'report']]);
    }

    /**
     * @param  list<class-string<Extension>>  $extensions
     * @param  array{bool, bool, bool}  $expected
     */
    #[Test]
    #[DataProvider('capabilities')]
    public function it_tells_the_extension_what_the_engine_can_do(?string $driver, array $extensions, array $expected): void
    {
        if ($driver !== null) {
            config(['scout.driver' => $driver]);
        }

        config(['rest.extensions' => $extensions]);

        $this->search('vouchers', ['text' => ['value' => 'report']])->assertOk();

        $context = EngineLog::$contexts[0];
        $this->assertInstanceOf(EngineRestrictionContext::class, $context);
        $this->assertSame($expected, [$context->matchesStringLists, $context->rowsRestrictedInSql, $context->comparesColumns]);
    }

    /** @return array<string, array{?string, list<class-string<Extension>>, array{bool, bool, bool}}> */
    public static function capabilities(): array
    {
        return [
            'the collection engine' => [null, [GuestlessEngineExtension::class], [false, false, true]],
            'the database engine without SQL restriction' => ['database', [GuestlessEngineExtension::class], [false, false, true]],
            'the database engine with the SQL restriction of another extension' => ['database', [GuestlessEngineExtension::class, RedRowsExtension::class], [false, false, true]],
            'the database engine with its own SQL restriction' => ['database', [SqlAwareEngineExtension::class], [false, true, true]],
            'the collection engine with its own SQL restriction' => [null, [SqlAwareEngineExtension::class], [false, true, true]],
        ];
    }

    #[Test]
    #[DataProvider('engines')]
    public function it_gives_the_extension_the_engine_that_runs_the_search(?string $driver, string $class): void
    {
        if ($driver !== null) {
            config(['scout.driver' => $driver]);
        }

        config(['rest.extensions' => [GuestlessEngineExtension::class]]);
        $engine = $this->app->make(EngineManager::class)->engine();
        $user = new GenericUser(['id' => 1]);

        $this->actingAs($user)->search('vouchers', ['text' => ['value' => 'report']])->assertOk();
        Rest::search(VoucherResource::class)->as($user)->payload(['text' => ['value' => 'report']])->page();
        $this->postJson('/api/vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report']]])->assertOk();

        $this->assertInstanceOf($class, $engine);
        $this->assertSame([$engine, $engine, $engine], array_map(fn (object $context): object => $context instanceof EngineRestrictionContext ? $context->engine : $context, EngineLog::$contexts));
    }

    /** @return array<string, array{?string, string}> */
    public static function engines(): array
    {
        return [
            'the engine of the test' => [null, RecordingCollectionEngine::class],
            'the database driver' => ['database', 'Laravel\\Scout\\Engines\\DatabaseEngine'],
            'the collection driver' => ['collection', 'Laravel\\Scout\\Engines\\CollectionEngine'],
        ];
    }

    #[Test]
    public function it_gives_the_extension_the_engine_of_another_engine_text_search(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('plain-engine-vouchers', PlainEngineVoucherController::class)->register());
        config(['rest.extensions' => [GuestlessEngineExtension::class]]);

        $this->actingAs(new GenericUser(['id' => 1]))->search('plain-engine-vouchers', ['text' => ['value' => 'report']])->assertOk();

        $this->assertSame(['PlainEngineVoucherResource'], EngineLog::$calls);
        $this->assertSame([$this->engine], array_map(fn (object $context): object => $context instanceof EngineRestrictionContext ? $context->engine : $context, EngineLog::$contexts));
    }

    #[Test]
    public function it_refuses_an_engine_restriction_when_the_model_gives_no_engine(): void
    {
        config(['rest.extensions' => [GuestlessEngineExtension::class]]);
        $resource = $this->resource(VoucherResource::class);
        $model = ScoutModel::tryFrom(new class extends Voucher
        {
            #[\Override]
            public function searchableUsing(): mixed
            {
                return null;
            }
        });
        $this->assertNotNull($model);
        $profile = new PlainProfile;
        $query = new EngineQuery(new EngineIndex($model, $profile, IndexAttributes::of(TextTarget::of($resource), $profile, ['team'], [], []), VoucherResource::class), new TextQuery('report'));

        $this->expectExceptionObject(InvalidTextSearchException::engineNotAnObject(VoucherResource::class, 'null'));

        EngineRestrictions::of($resource)?->applyTo($query);
    }

    #[Test]
    public function it_keeps_the_rows_of_the_extensions_from_an_instruction(): void
    {
        config(['scout.driver' => 'database', 'rest.extensions' => [HiddenRowsExtension::class, RowReplacingExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineQueryException::repeatedRowRestriction(VoucherResource::class, [['hidden', HiddenRowsExtension::class]]));

        $this->search('vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'rr.replace-rows']]]);
    }

    #[Test]
    public function it_names_the_resource_and_the_extensions_of_a_repeated_row_restriction(): void
    {
        $this->assertSame(
            'The engine query of '.VoucherResource::class.' already carries the row restrictions of the [hidden] extension '.HiddenRowsExtension::class.' and the [red] extension '.RedRowsExtension::class.', and the package passes them once per engine query. Restrict the engine query with where(), whereIn() or whereNotIn() in ScoutEngine::perimeter() or in an instruction instead of restrictRows().',
            InvalidEngineQueryException::repeatedRowRestriction(VoucherResource::class, [['red', RedRowsExtension::class], ['hidden', HiddenRowsExtension::class]])->getMessage(),
        );
    }

    #[Test]
    public function it_refuses_an_attribute_that_the_index_does_not_declare_and_names_the_registered_class(): void
    {
        config(['rest.extensions' => [OwnerEngineRestriction::class]]);
        $this->app?->bind(OwnerEngineRestriction::class, static fn (): OwnerEngineRestriction => new class extends OwnerEngineRestriction {});
        $this->withoutExceptionHandling();

        try {
            $this->search('vouchers', ['text' => ['value' => 'report']]);
            $this->fail('The undeclared attribute was accepted.');
        } catch (InvalidEngineExtensionException $exception) {
            $this->assertSame(InvalidEngineExtensionException::refusedEngineCondition('owner', OwnerEngineRestriction::class, VoucherResource::class, InvalidEngineQueryException::undeclaredAttribute('owner'))->getMessage(), $exception->getMessage());
            $this->assertTrue($exception->concerns(OwnerEngineRestriction::class));
        }
    }

    #[Test]
    public function it_names_the_extension_in_a_refused_condition(): void
    {
        $exception = InvalidEngineExtensionException::refusedEngineCondition('owner', OwnerEngineRestriction::class, VoucherResource::class, InvalidEngineQueryException::undeclaredAttribute('owner'));

        $this->assertSame(
            'The [owner] extension '.OwnerEngineRestriction::class.' restricts the engine search of '.VoucherResource::class.' with a condition that the engine query refuses: The engine query has no attribute [owner]. Declare it with ScoutEngine::filterable(), among the attributes of ScoutEngine::perimeter(), or in indexAttributes() of an extension that implements DeclaresIndexAttributes.',
            $exception->getMessage(),
        );
        $this->assertSame([true, false], [$exception->concerns(OwnerEngineRestriction::class), $exception->concerns('owner')]);
    }

    #[Test]
    public function it_skips_an_extension_that_does_not_apply(): void
    {
        config(['rest.extensions' => [UnappliedEngineRestriction::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 3], 4));
        $this->assertSame([], EngineLog::$calls);
    }

    #[Test]
    public function it_runs_no_engine_restriction_without_text(): void
    {
        config(['rest.extensions' => [GuestlessEngineExtension::class]]);

        $this->search('vouchers', ['filters' => [['field' => 'title', 'value' => 'Budget']]])->assertOk()->assertJsonPath('data.*.title', ['Budget']);

        $this->assertSame([], EngineLog::$calls);
    }

    #[Test]
    public function it_builds_no_extension_for_an_engine_search_until_one_is_configured(): void
    {
        $resolved = [];
        $this->app->beforeResolving(function (string $abstract) use (&$resolved): void {
            if (class_exists($abstract) && is_subclass_of($abstract, Extension::class)) {
                $resolved[] = $abstract;
            }
        });

        $this->search('vouchers', ['text' => ['value' => 'report']])->assertOk();
        $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]])->assertOk();

        $this->assertSame([[], [], []], [$resolved, ExtensionLog::$built, EngineLog::$calls]);

        config(['rest.extensions' => [TeamIndexExtension::class]]);

        $this->search('vouchers', ['text' => ['value' => 'report']])->assertOk();

        $this->assertSame([[TeamIndexExtension::class], ['VoucherResource']], [$resolved, EngineLog::$calls]);
    }

    /** @return Builder<Model> */
    private function builder(): Builder
    {
        $this->assertCount(1, $this->engine->searches);

        return $this->engine->searches[0];
    }
}

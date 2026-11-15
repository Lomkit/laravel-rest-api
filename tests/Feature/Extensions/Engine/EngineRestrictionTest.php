<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Extensions\Contexts\EngineRestriction;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CheapEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ComparedPriceVoucherController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\GuestlessEngineExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\NumberEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OwnerEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PriceEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedTeamEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RowReplacingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TeamIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\UnappliedEngineRestriction;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnboundedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnboundedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

#[Group('scout')]
class EngineRestrictionTest extends EngineSearchTestCase
{
    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EngineLog::reset();
        ExtensionLog::reset();
        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
    }

    #[\Override]
    protected function tearDown(): void
    {
        EngineLog::reset();
        ExtensionLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_restricts_an_engine_search_of_a_resource_without_perimeter(): void
    {
        config(['rest.extensions' => ['team' => TeamIndexExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 1, 3], 3));
        $this->assertSame(['team' => ['blue']], $this->builder()->whereNotIns);
    }

    #[Test]
    public function it_restricts_after_the_perimeter_and_before_the_client_filters(): void
    {
        config(['rest.extensions' => ['red' => RedTeamEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['blue', 'red']]));

        $response = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'number', 'operator' => 'in', 'value' => [1, 4]]]]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $this->assertSame(['team' => ['red'], 'number' => [1, 4]], $this->builder()->whereIns);
    }

    #[Test]
    public function it_applies_the_extension_between_the_perimeter_and_the_client(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('compared-price-vouchers', ComparedPriceVoucherController::class)->register());
        config(['rest.extensions' => ['cheap' => CheapEngineRestriction::class]]);

        $response = $this->search('compared-price-vouchers', [
            'text' => ['value' => 'report'],
            'filters' => [['field' => 'price', 'operator' => '>=', 'value' => 1]],
            'instructions' => [['name' => 'priced-up-to', 'fields' => [['name' => 'max', 'value' => 30]]]],
        ]);

        $response->assertOk()->assertExactJson($this->page([4, 1, 3], 3));
        $wheres = array_values(array_filter($this->builder()->wheres, fn (array $where): bool => $where['field'] !== '__soft_deleted'));
        $this->assertSame([['>', 0], ['<', 35], ['>=', 1], ['<', 30]], array_map(fn (array $where): array => [$where['operator'], $where['value']], $wheres));
    }

    /** @param array<string, class-string<Extension>> $extensions */
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

    /** @return array<string, array{array<string, class-string<Extension>>}> */
    public static function orders(): array
    {
        return [
            'in name order' => [['a' => NumberEngineRestriction::class, 'b' => PriceEngineRestriction::class]],
            'in reverse order' => [['b' => PriceEngineRestriction::class, 'a' => NumberEngineRestriction::class]],
        ];
    }

    #[Test]
    public function it_keeps_a_value_an_extension_excludes_out_of_a_client_filter(): void
    {
        config(['rest.extensions' => ['b' => PriceEngineRestriction::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'number', 'operator' => 'in', 'value' => [4, 5]]]]);

        $response->assertOk()->assertExactJson($this->page([4], 1));
        $this->assertSame(['number' => [5]], $this->builder()->whereNotIns);
    }

    #[Test]
    public function it_narrows_a_client_in_filter_to_the_values_of_the_extension(): void
    {
        config(['rest.extensions' => ['a' => NumberEngineRestriction::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'number', 'operator' => 'in', 'value' => [2, 3]]]]);

        $response->assertOk()->assertExactJson($this->page([1], 1));
        $this->assertSame(['number' => [3]], $this->builder()->whereIns);
    }

    #[Test]
    public function it_returns_an_empty_page_without_calling_the_engine_for_a_guest(): void
    {
        config(['rest.extensions' => ['guestless' => GuestlessEngineExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([], 0));
        $this->assertSame([], $this->engine->searches);
        $this->assertSame([null], EngineLog::$users);
    }

    #[Test]
    public function it_runs_an_action_on_no_row_for_a_guest(): void
    {
        config(['rest.extensions' => ['guestless' => GuestlessEngineExtension::class]]);

        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->assertSame([], $this->engine->searches);
        $this->assertSame([5], Voucher::withTrashed()->where('active', false)->pluck('id')->all());
    }

    #[Test]
    public function it_searches_for_a_user_that_the_extension_lets_through(): void
    {
        config(['rest.extensions' => ['guestless' => GuestlessEngineExtension::class]]);

        $response = $this->actingAs(new GenericUser(['id' => 1]))->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 3], 4));
    }

    #[Test]
    public function it_gives_the_extension_the_gate_user_of_rest_search_as(): void
    {
        config(['rest.extensions' => ['guestless' => GuestlessEngineExtension::class]]);
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
    public function it_gives_the_extension_the_user_of_the_request(): void
    {
        config(['rest.extensions' => ['guestless' => GuestlessEngineExtension::class]]);
        $user = new GenericUser(['id' => 3]);

        $this->actingAs($user)->search('vouchers', ['text' => ['value' => 'report']])->assertOk();

        $this->assertSame([$user], EngineLog::$users);
    }

    #[Test]
    public function it_restricts_the_targets_of_an_action_once(): void
    {
        config(['rest.extensions' => ['team' => TeamIndexExtension::class]]);

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
        config(['rest.extensions' => ['guestless' => GuestlessEngineExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::missingPerimeter(UnboundedVoucherResource::class));

        $this->search('unbounded-vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_offers_only_where_clauses_and_match_nothing(): void
    {
        $methods = array_map(fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(EngineRestriction::class))->getMethods(ReflectionMethod::IS_PUBLIC));

        $this->assertSame(['where', 'whereIn', 'whereNotIn', 'matchNothing'], array_values(array_diff($methods, ['__construct'])));
        $this->assertSame(['query', 'resource', 'user', 'matchesStringLists', 'rowsRestrictedInSql'], array_map(
            fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(EngineRestrictionContext::class))->getProperties(ReflectionProperty::IS_PUBLIC),
        ));
    }

    /** @param array{bool, bool} $expected */
    #[Test]
    #[DataProvider('capabilities')]
    public function it_tells_the_extension_what_the_engine_can_do(?string $driver, array $expected): void
    {
        if ($driver !== null) {
            config(['scout.driver' => $driver]);
        }

        config(['rest.extensions' => ['guestless' => GuestlessEngineExtension::class]]);

        $this->search('vouchers', ['text' => ['value' => 'report']])->assertOk();

        $context = EngineLog::$contexts[0];
        $this->assertInstanceOf(EngineRestrictionContext::class, $context);
        $this->assertSame($expected, [$context->matchesStringLists, $context->rowsRestrictedInSql]);
    }

    /** @return array<string, array{?string, array{bool, bool}}> */
    public static function capabilities(): array
    {
        return [
            'the collection engine' => [null, [false, false]],
            'the database engine' => ['database', [false, true]],
        ];
    }

    #[Test]
    public function it_keeps_the_rows_of_the_extensions_from_an_instruction(): void
    {
        config(['scout.driver' => 'database', 'rest.extensions' => ['hidden' => HiddenRowsExtension::class, 'rr' => RowReplacingExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineQueryException::repeatedRowRestriction());

        $this->search('vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'rr.replace-rows']]]);
    }

    #[Test]
    public function it_refuses_an_attribute_that_the_index_does_not_declare(): void
    {
        config(['rest.extensions' => ['owner' => OwnerEngineRestriction::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineQueryException::undeclaredAttribute('owner'));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_names_every_way_to_declare_an_attribute(): void
    {
        $this->assertSame(
            'The engine query has no attribute [owner]. Declare it with ScoutEngine::filterable(), among the attributes of ScoutEngine::perimeter(), or in indexAttributes() of an extension that implements DeclaresIndexAttributes.',
            InvalidEngineQueryException::undeclaredAttribute('owner')->getMessage(),
        );
    }

    #[Test]
    public function it_skips_an_extension_that_does_not_apply(): void
    {
        config(['rest.extensions' => ['unapplied' => UnappliedEngineRestriction::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 3], 4));
        $this->assertSame([], EngineLog::$calls);
    }

    #[Test]
    public function it_runs_no_engine_restriction_without_text(): void
    {
        config(['rest.extensions' => ['guestless' => GuestlessEngineExtension::class]]);

        $this->search('vouchers', ['filters' => [['field' => 'title', 'value' => 'Budget']]])->assertOk()->assertJsonPath('data.*.title', ['Budget']);

        $this->assertSame([], EngineLog::$calls);
    }

    #[Test]
    public function it_builds_no_extension_for_an_engine_search_without_one(): void
    {
        $resolved = [];
        $this->app->beforeResolving(function (string $abstract) use (&$resolved): void {
            $resolved[] = $abstract;
        });

        $this->search('vouchers', ['text' => ['value' => 'report']])->assertOk();
        $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]])->assertOk();

        $this->assertSame([], array_values(array_filter(
            $resolved,
            fn (string $abstract): bool => class_exists($abstract) && is_subclass_of($abstract, Extension::class),
        )));
        $this->assertSame([], ExtensionLog::$built);
    }

    /** @return Builder<Model> */
    private function builder(): Builder
    {
        $this->assertCount(1, $this->engine->searches);

        return $this->engine->searches[0];
    }
}

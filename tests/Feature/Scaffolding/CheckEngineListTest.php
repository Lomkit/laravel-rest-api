<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\KeylessListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\KeylessListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\OpenListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\OpenListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PerimeterListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PerimeterListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TiedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TiedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherController;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[Group('scout')]
#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
final class CheckEngineListTest extends ScaffoldingTestCase
{
    use ResetsExtensionStubs;
    use RunsTheQuickStart;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Ledger];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [ScoutServiceProvider::class, RestServiceProvider::class];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('listed-vouchers', ListedVoucherController::class)->middleware('auth:sanctum')->register());
    }

    #[Test]
    public function it_lists_the_resources_that_serve_their_lists_through_the_engine(): void
    {
        config(['scout.driver' => 'collection']);
        Route::prefix('api')->group(function (): void {
            Rest::resource('vouchers', VoucherController::class)->middleware('auth:sanctum')->register();
            Rest::resource('open-listed-vouchers', OpenListedVoucherController::class)->middleware('auth:sanctum')->register();
        });

        Artisan::call('rest:check', ['controllers' => [OpenListedVoucherController::class, VoucherController::class], '--json' => true], $output = new BufferedOutput);
        $lists = json_decode($output->fetch(), true)['engine_lists'];
        Artisan::call('rest:check', ['controllers' => [OpenListedVoucherController::class]], $text = new BufferedOutput);

        $this->assertSame(['resources' => [OpenListedVoucherResource::class], 'problems' => [], 'notes' => []], $lists);
        $this->assertStringContainsString("  engine lists ... 1 resource lists through the search engine\n  ".OpenListedVoucherResource::class." ... lists through the search engine\n", $this->summary($text->fetch()));
    }

    #[Test]
    public function it_notes_a_resource_that_serves_its_lists_and_overrides_perimeter_query(): void
    {
        config(['scout.driver' => 'collection']);
        Route::prefix('api')->group(fn () => Rest::resource('perimeter-listed-vouchers', PerimeterListedVoucherController::class)->middleware('auth:sanctum')->register());
        $note = self::unappliedPerimeterQuery(PerimeterListedVoucherResource::class);

        [$exitCode, $lists] = $this->lists(PerimeterListedVoucherController::class);
        Artisan::call('rest:check', ['controllers' => [PerimeterListedVoucherController::class]], $text = new BufferedOutput);

        $this->assertSame([0, ['resources' => [PerimeterListedVoucherResource::class], 'problems' => [], 'notes' => [$note]]], [$exitCode, $lists]);
        $this->assertStringContainsString(
            "  engine lists ... 1 resource lists through the search engine\n"
            .'  ⇂ '.rtrim($note, '.')."\n"
            .'  '.PerimeterListedVoucherResource::class." ... lists through the search engine\n",
            $this->summary($text->fetch()),
        );
    }

    #[Test]
    public function it_notes_a_resource_that_serves_its_lists_on_the_null_driver(): void
    {
        config(['scout.driver' => 'null']);
        Route::prefix('api')->group(fn () => Rest::resource('open-listed-vouchers', OpenListedVoucherController::class)->middleware('auth:sanctum')->register());
        $note = 'The resource ['.OpenListedVoucherResource::class.'] serves its lists through its search engine, but its model uses the Scout null driver ['.NullEngine::class.'] in this environment, so its lists will be empty. Run rest:check with the Scout driver of production (SCOUT_DRIVER).';

        [$exitCode, $lists] = $this->lists(OpenListedVoucherController::class);
        Artisan::call('rest:check', ['controllers' => [OpenListedVoucherController::class]], $text = new BufferedOutput);

        $this->assertSame([0, ['resources' => [OpenListedVoucherResource::class], 'problems' => [], 'notes' => [$note]]], [$exitCode, $lists]);
        $this->assertStringContainsString("  engine lists ... 1 resource lists through the search engine\n  ⇂ ".rtrim($note, '.')."\n", $this->summary($text->fetch()));
    }

    #[Test]
    public function it_notes_a_resource_whose_sorts_leave_out_the_scout_key(): void
    {
        config(['scout.driver' => 'collection']);
        $note = KeylessListedVoucherResource::class.' serves its lists through its search engine but declares neither ->tieBreaker() nor its key [code] in sortable(): a list sent without a sort follows the engine order, which can repeat or skip rows between pages. Declare ->tieBreaker() with a unique attribute that the index sorts, or add [code] to sortable() (and to the sortable settings of the index): a list sent without a sort then follows it, descending.';

        [$exitCode, $lists, $text] = $this->keyless();

        $this->assertSame([0, ['resources' => [KeylessListedVoucherResource::class], 'problems' => [], 'notes' => [$note]]], [$exitCode, $lists]);
        $this->assertStringContainsString(
            "  engine lists ... 1 resource lists through the search engine\n"
            .'  ⇂ '.rtrim($note, '.')."\n"
            .'  '.KeylessListedVoucherResource::class." ... lists through the search engine\n",
            $text,
        );
    }

    #[Test]
    public function it_notes_the_unstable_order_of_an_engine_that_sorts_no_list(): void
    {
        $this->app?->make(EngineManager::class)->extend('algolia', fn (): FakeAlgolia4Engine => new FakeAlgolia4Engine(new SettingsClient(fn (): array => [])));
        config(['scout.driver' => 'algolia']);
        $note = KeylessListedVoucherResource::class.' serves its lists through its search engine ['.FakeAlgolia4Engine::class.'], which takes no sort without text: its lists follow the ranking of the index, which can repeat or skip rows between pages. End the customRanking of the index on a unique attribute, such as [code], so that the order is stable.';

        [$exitCode, $lists, $text] = $this->keyless();

        $this->assertSame([0, ['resources' => [KeylessListedVoucherResource::class], 'problems' => [], 'notes' => [$note]]], [$exitCode, $lists]);
        $this->assertStringContainsString("  engine lists ... 1 resource lists through the search engine\n  ⇂ ".rtrim($note, '.')."\n", $text);
    }

    #[Test]
    public function it_reports_a_resource_that_serves_its_lists_on_a_profile_that_does_not_list_without_text(): void
    {
        $this->app?->make(EngineManager::class)->extend('foreign', fn (): ForeignEngine => new ForeignEngine);
        config(['scout.driver' => 'foreign', 'rest.extensions' => [ForeignEngineProfiles::class]]);

        [$exitCode, $lists] = $this->lists(ListedVoucherController::class);

        $this->assertSame([1, ['resources' => [], 'problems' => [InvalidTextSearchException::unlistedEngine(ListedVoucherResource::class, ForeignEngine::class)->getMessage()], 'notes' => [self::unappliedPerimeterQuery(ListedVoucherResource::class)]]], [$exitCode, $lists]);
    }

    #[Test]
    public function it_asks_for_a_tie_breaker_on_typesense_and_never_for_its_document_id(): void
    {
        $this->useTypesense();
        $note = ListedVoucherResource::class.' serves its lists through its search engine ['.FakeTypesenseEngine::class.'] without ->tieBreaker(): rows with equal sort values can repeat or skip between pages. Declare ->tieBreaker() with a unique attribute that the index sorts, never [id], which the engine does not sort: every list then ends its order on it, descending, and the client keeps one sort less.';

        $this->assertSame(['resources' => [ListedVoucherResource::class], 'problems' => [], 'notes' => [$note, self::unappliedPerimeterQuery(ListedVoucherResource::class)]], $this->lists(ListedVoucherController::class)[1]);
    }

    #[Test]
    public function it_notes_nothing_for_a_string_tie_breaker_on_typesense_or_the_collection_engine(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('tied-vouchers', TiedVoucherController::class)->middleware('auth:sanctum')->register());
        $this->useTypesense();
        $typesense = $this->lists(TiedVoucherController::class);
        config(['scout.driver' => 'collection']);

        $this->assertSame(['resources' => [TiedVoucherResource::class], 'problems' => [], 'notes' => [self::unappliedPerimeterQuery(TiedVoucherResource::class)]], $typesense[1]);
        $this->assertSame(['resources' => [TiedVoucherResource::class], 'problems' => [], 'notes' => [self::unappliedPerimeterQuery(TiedVoucherResource::class)]], $this->lists(TiedVoucherController::class)[1]);
    }

    #[Test]
    public function it_notes_on_turbopuffer_only_that_a_client_sort_keeps_no_tie_breaker(): void
    {
        $this->app?->make(EngineManager::class)->extend('turbopuffer', fn (): FakeTurbopufferEngine => new FakeTurbopufferEngine);
        config(['scout.driver' => 'turbopuffer']);
        $note = ListedVoucherResource::class.' serves its lists through its search engine ['.FakeTurbopufferEngine::class.'], which takes one sort without text: a list that the client sorts follows that sort alone, so its order is not stable unless the sorted attribute is unique.';

        [$exitCode, $keyless] = $this->keyless();

        $this->assertSame(['resources' => [ListedVoucherResource::class], 'problems' => [], 'notes' => [$note, self::unappliedPerimeterQuery(ListedVoucherResource::class)]], $this->lists(ListedVoucherController::class)[1]);
        $this->assertSame([0, ['resources' => [KeylessListedVoucherResource::class], 'problems' => [], 'notes' => []]], [$exitCode, $keyless]);
    }

    #[Test]
    public function it_reports_a_tie_breaker_on_an_engine_that_takes_no_sort_without_text(): void
    {
        $this->app?->make(EngineManager::class)->extend('algolia', fn (): FakeAlgolia4Engine => new FakeAlgolia4Engine(new SettingsClient(fn (): array => [])));
        config(['scout.driver' => 'algolia']);
        Route::prefix('api')->group(fn () => Rest::resource('tied-vouchers', TiedVoucherController::class)->middleware('auth:sanctum')->register());
        $problem = InvalidTextSearchException::unsortedTieBreaker(TiedVoucherResource::class, 'title', FakeAlgolia4Engine::class)->getMessage();

        $this->assertSame(['resources' => [TiedVoucherResource::class], 'problems' => [$problem], 'notes' => [self::unappliedPerimeterQuery(TiedVoucherResource::class)]], $this->lists(TiedVoucherController::class)[1]);
    }

    /** @return array{int, mixed} */
    private function lists(string $controller): array
    {
        $exitCode = Artisan::call('rest:check', ['controllers' => [$controller], '--json' => true], $output = new BufferedOutput);

        return [$exitCode, json_decode($output->fetch(), true)['engine_lists']];
    }

    /** @return array{int, mixed, string} */
    private function keyless(): array
    {
        Route::prefix('api')->group(fn () => Rest::resource('keyless-listed-vouchers', KeylessListedVoucherController::class)->middleware('auth:sanctum')->register());
        [$exitCode, $lists] = $this->lists(KeylessListedVoucherController::class);
        Artisan::call('rest:check', ['controllers' => [KeylessListedVoucherController::class]], $text = new BufferedOutput);

        return [$exitCode, $lists, $this->summary($text->fetch())];
    }

    private function useTypesense(): void
    {
        $this->app?->make(EngineManager::class)->extend('typesense', fn (): FakeTypesenseEngine => new FakeTypesenseEngine);
        config(['scout.driver' => 'typesense', 'scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
    }

    private static function unappliedPerimeterQuery(string $resource): string
    {
        return $resource.' overrides perimeterQuery(), which does not apply to its lists: they are served through its search engine, restricted by ScoutEngine::perimeter() and the RestrictsEngineQueries extensions. Mirror the perimeter in ScoutEngine::perimeter().';
    }
}

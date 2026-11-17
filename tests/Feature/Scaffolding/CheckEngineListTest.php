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
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\OpenListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\OpenListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PerimeterListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PerimeterListedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
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
    public function it_reports_a_resource_that_serves_its_lists_on_a_profile_that_does_not_list_without_text(): void
    {
        $this->app?->make(EngineManager::class)->extend('foreign', fn (): ForeignEngine => new ForeignEngine);
        config(['scout.driver' => 'foreign', 'rest.extensions' => [ForeignEngineProfiles::class]]);

        [$exitCode, $lists] = $this->lists(ListedVoucherController::class);

        $this->assertSame([1, ['resources' => [], 'problems' => [InvalidTextSearchException::unlistedEngine(ListedVoucherResource::class, ForeignEngine::class)->getMessage()], 'notes' => [self::unappliedPerimeterQuery(ListedVoucherResource::class)]]], [$exitCode, $lists]);
    }

    /** @return array{int, mixed} */
    private function lists(string $controller): array
    {
        $exitCode = Artisan::call('rest:check', ['controllers' => [$controller], '--json' => true], $output = new BufferedOutput);

        return [$exitCode, json_decode($output->fetch(), true)['engine_lists']];
    }

    private static function unappliedPerimeterQuery(string $resource): string
    {
        return $resource.' overrides perimeterQuery(), which does not apply to its lists: they are served through its search engine, restricted by ScoutEngine::perimeter() and the RestrictsEngineQueries extensions. Mirror the perimeter in ScoutEngine::perimeter().';
    }
}

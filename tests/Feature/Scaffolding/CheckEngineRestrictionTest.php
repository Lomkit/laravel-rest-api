<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedEverywhereExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Feature\Scaffolding\Stubs\CheckedVoucherController;
use Lomkit\Rest\Tests\Feature\Scaffolding\Stubs\CheckedVoucherResource;
use Lomkit\Rest\Tests\Feature\Scaffolding\Stubs\RouteBoundVoucherController;
use Lomkit\Rest\Tests\Feature\Scaffolding\Stubs\RouteBoundVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainEngineVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainEngineVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainProfile;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[Group('scout')]
#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
final class CheckEngineRestrictionTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        RestrictionLog::reset();
        RouteBoundVoucherResource::$textCalls = 0;
    }

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

        Route::prefix('api')->group(fn () => Rest::resource('checked-vouchers', CheckedVoucherController::class)->middleware('auth:sanctum')->register());
    }

    #[Test]
    public function it_reports_the_registered_class_of_a_restriction_bound_to_a_subclass(): void
    {
        $this->useForeignEngine([RedRowsExtension::class]);
        $this->app?->bind(RedRowsExtension::class, static fn (): RedRowsExtension => new class extends RedRowsExtension {});

        [$exitCode, $rows] = $this->rows();

        $this->assertSame([1, [InvalidTextSearchException::unrestrictedEngine(CheckedVoucherResource::class, 'red', RedRowsExtension::class, ForeignEngine::class)->getMessage()]], [$exitCode, $rows['problems']]);
    }

    #[Test]
    public function it_reports_a_restriction_that_an_engine_search_cannot_apply(): void
    {
        $this->useForeignEngine([RedRowsExtension::class]);
        $problem = InvalidTextSearchException::unrestrictedEngine(CheckedVoucherResource::class, 'red', RedRowsExtension::class, ForeignEngine::class)->getMessage();

        [$exitCode, $rows] = $this->rows();
        $text = Artisan::call('rest:check', ['controllers' => [CheckedVoucherController::class]], $output = new BufferedOutput);

        $this->assertSame([1, ['perimeter' => [CheckedVoucherResource::class], 'without_perimeter' => [], 'problems' => [$problem]]], [$exitCode, $rows]);
        $this->assertSame(1, $text);
        $this->assertStringContainsString("  rows ... FAILED\n  ⇂ ".rtrim($problem, '.')."\n", $this->summary($output->fetch()));
    }

    #[Test]
    public function it_reports_a_restriction_on_a_custom_engine_text_search(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('plain-engine-vouchers', PlainEngineVoucherController::class)->middleware('auth:sanctum')->register());
        config(['rest.extensions' => [HiddenRowsExtension::class]]);
        $problem = InvalidTextSearchException::unrestrictedEngine(PlainEngineVoucherResource::class, 'hidden', HiddenRowsExtension::class, PlainProfile::NAME)->getMessage();

        $exitCode = Artisan::call('rest:check', ['controllers' => [PlainEngineVoucherController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame([1, [$problem]], [$exitCode, json_decode($output->fetch(), true)['rows']['problems']]);
    }

    #[Test]
    public function it_reports_nothing_on_the_database_engine(): void
    {
        config(['scout.driver' => 'database', 'rest.extensions' => [RedRowsExtension::class]]);

        $this->assertSame([0, ['perimeter' => [CheckedVoucherResource::class], 'without_perimeter' => [], 'problems' => []]], $this->rows());
    }

    #[Test]
    public function it_reports_nothing_for_a_restriction_that_also_restricts_the_engine(): void
    {
        $this->useForeignEngine([RedEverywhereExtension::class]);

        $this->assertSame([0, ['perimeter' => [CheckedVoucherResource::class], 'without_perimeter' => [], 'problems' => []]], $this->rows());
    }

    #[Test]
    public function it_reports_the_restriction_when_the_engine_cannot_be_resolved(): void
    {
        config(['scout.driver' => 'missing', 'rest.extensions' => [RedRowsExtension::class]]);

        [$exitCode, $rows] = $this->rows();

        $this->assertSame(1, $exitCode);
        $this->assertSame(['The [red] extension '.RedRowsExtension::class.' restricts the rows of '.CheckedVoucherResource::class.' in SQL but does not implement Lomkit\\Rest\\Extensions\\Kinds\\RestrictsEngineQueries, and the text search or the Scout engine of '.CheckedVoucherResource::class.' cannot be resolved (Driver [missing] not supported.). A text search of '.CheckedVoucherResource::class.' is refused on an engine that does not apply SQL restrictions. Implement RestrictsEngineQueries in '.RedRowsExtension::class.' to restrict the engine search too, index the model with the Scout database or collection driver, leave '.CheckedVoucherResource::class.' out of the extension with appliesTo(), or add '.RedRowsExtension::class.' to the [rest.disabled_extensions] config.'], $rows['problems']);
    }

    #[Test]
    public function it_leaves_a_broken_extension_list_to_the_extensions_section(): void
    {
        $this->useForeignEngine([RedRowsExtension::class, 'Lomkit\Rest\Tests\Fixtures\Extensions\MissingExtension']);

        $exitCode = Artisan::call('rest:check', ['controllers' => [CheckedVoucherController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertSame(1, $exitCode);
        $this->assertSame([], $report['rows']['problems']);
        $this->assertCount(1, $report['extensions']['problems']);
    }

    #[Test]
    public function it_does_not_read_the_text_search_without_an_extension(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('route-bound-vouchers', RouteBoundVoucherController::class)->middleware('auth:sanctum')->register());
        config(['scout.driver' => 'collection', 'rest.extensions' => []]);

        $exitCode = Artisan::call('rest:check', ['controllers' => [RouteBoundVoucherController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame([0, ['perimeter' => [RouteBoundVoucherResource::class], 'without_perimeter' => [], 'problems' => []], 0], [$exitCode, json_decode($output->fetch(), true)['rows'], RouteBoundVoucherResource::$textCalls]);
    }

    #[Test]
    public function it_reports_a_text_search_that_throws_in_the_console(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('route-bound-vouchers', RouteBoundVoucherController::class)->middleware('auth:sanctum')->register());
        config(['scout.driver' => 'collection', 'rest.extensions' => [RedRowsExtension::class]]);

        $exitCode = Artisan::call('rest:check', ['controllers' => [RouteBoundVoucherController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame(
            ['The [red] extension '.RedRowsExtension::class.' restricts the rows of '.RouteBoundVoucherResource::class.' in SQL but does not implement Lomkit\\Rest\\Extensions\\Kinds\\RestrictsEngineQueries, and the text search or the Scout engine of '.RouteBoundVoucherResource::class.' cannot be resolved (text() needs the request route). A text search of '.RouteBoundVoucherResource::class.' is refused on an engine that does not apply SQL restrictions. Implement RestrictsEngineQueries in '.RedRowsExtension::class.' to restrict the engine search too, index the model with the Scout database or collection driver, leave '.RouteBoundVoucherResource::class.' out of the extension with appliesTo(), or add '.RedRowsExtension::class.' to the [rest.disabled_extensions] config.'],
            json_decode($output->fetch(), true)['rows']['problems'],
        );
    }

    /** @param list<string> $extensions */
    private function useForeignEngine(array $extensions): void
    {
        $this->app->make(EngineManager::class)->extend('foreign', fn (): ForeignEngine => new ForeignEngine);
        config(['scout.driver' => 'foreign', 'rest.extensions' => [ForeignEngineProfiles::class, ...$extensions]]);
    }

    /** @return array{int, mixed} */
    private function rows(): array
    {
        $exitCode = Artisan::call('rest:check', ['controllers' => [CheckedVoucherController::class], '--json' => true], $output = new BufferedOutput);

        return [$exitCode, json_decode($output->fetch(), true)['rows']];
    }
}

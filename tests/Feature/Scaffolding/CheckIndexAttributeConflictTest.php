<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DeclaredIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ThrowingIndexExtension;
use Lomkit\Rest\Tests\Feature\Scaffolding\Stubs\IndexedTeamVoucherController;
use Lomkit\Rest\Tests\Feature\Scaffolding\Stubs\IndexedTeamVoucherResource;
use Lomkit\Rest\Tests\Feature\Scaffolding\Stubs\PerimeterTeamVoucherController;
use Lomkit\Rest\Tests\Feature\Scaffolding\Stubs\PerimeterTeamVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Lomkit\Rest\Text\Engine\ResourceIndexList;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[Group('scout')]
#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
final class CheckIndexAttributeConflictTest extends ScaffoldingTestCase
{
    use ResetsExtensionStubs;

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

        DeclaredIndexExtension::$declared = [FirstIndexExtension::class => [Text::make('team')]];
        config(['scout.driver' => 'collection', 'rest.extensions' => [FirstIndexExtension::class]]);
    }

    #[Test]
    public function it_reports_a_perimeter_attribute_that_an_extension_declares_with_another_type(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('perimeter-team-vouchers', PerimeterTeamVoucherController::class)->middleware('auth:sanctum')->register());
        $problem = PerimeterTeamVoucherResource::class.': '.InvalidEngineExtensionException::indexAttributeTakenByResource('first', FirstIndexExtension::class, PerimeterTeamVoucherResource::class, 'team', ResourceIndexList::Perimeter)->getMessage();

        $exitCode = Artisan::call('rest:check', ['controllers' => [PerimeterTeamVoucherController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame([1, [$problem]], [$exitCode, json_decode($output->fetch(), true)['rows']['problems']]);
    }

    #[Test]
    public function it_reports_an_index_attribute_that_an_extension_declares_with_another_type(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('indexed-team-vouchers', IndexedTeamVoucherController::class)->middleware('auth:sanctum')->register());
        $problem = IndexedTeamVoucherResource::class.': '.InvalidEngineExtensionException::indexAttributeTakenByResource('first', FirstIndexExtension::class, IndexedTeamVoucherResource::class, 'team', ResourceIndexList::IndexAttributes)->getMessage();

        $exitCode = Artisan::call('rest:check', ['controllers' => [IndexedTeamVoucherController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame([1, [$problem]], [$exitCode, json_decode($output->fetch(), true)['rows']['problems']]);
        $this->assertStringContainsString('which the indexAttributes() list of its ScoutEngine already declares', $problem);
    }

    #[Test]
    public function it_reports_an_extension_whose_index_attributes_throw_and_names_the_resource(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('indexed-team-vouchers', IndexedTeamVoucherController::class)->middleware('auth:sanctum')->register());
        config(['rest.extensions' => [ThrowingIndexExtension::class]]);

        $exitCode = Artisan::call('rest:check', ['controllers' => [IndexedTeamVoucherController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame([1, [IndexedTeamVoucherResource::class.': The tenant directory is not configured.']], [$exitCode, json_decode($output->fetch(), true)['rows']['problems']]);
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\FacetingVoucher;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\FacetingVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\FacetingVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\MisdeclaredTotalsVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\MisdeclaredTotalsVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[Group('scout')]
#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
final class CheckEngineTotalsTest extends ScaffoldingTestCase
{
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

    #[Test]
    public function it_reports_a_group_outside_the_filterable_attributes(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('misdeclared-vouchers', MisdeclaredTotalsVoucherController::class)->middleware('auth:sanctum')->register());
        config(['scout.driver' => 'collection']);

        $exitCode = Artisan::call('rest:check', ['controllers' => [MisdeclaredTotalsVoucherController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame([1, [MisdeclaredTotalsVoucherResource::class.': '.InvalidTextSearchException::ungroupableAttribute(MisdeclaredTotalsVoucherResource::class, 'title')->getMessage()]], [$exitCode, json_decode($output->fetch(), true)['rows']['problems']]);
    }

    #[Test]
    public function it_reports_a_typesense_model_that_sends_a_facet_parameter_of_a_resource_with_totals(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('faceting-vouchers', FacetingVoucherController::class)->middleware('auth:sanctum')->register());
        $this->app->make(EngineManager::class)->extend('typesense', fn (): FakeTypesenseEngine => new FakeTypesenseEngine);
        config(['scout.driver' => 'typesense']);

        $exitCode = Artisan::call('rest:check', ['controllers' => [FacetingVoucherController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame([1, [FacetingVoucherResource::class.': '.InvalidTextSearchException::ownedSearchParameter(FacetingVoucherResource::class, FacetingVoucher::class, 'facet_by')->getMessage()]], [$exitCode, json_decode($output->fetch(), true)['rows']['problems']]);
    }
}

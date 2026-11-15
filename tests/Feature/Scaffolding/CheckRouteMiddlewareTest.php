<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\GuardRoutes;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripController;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
final class CheckRouteMiddlewareTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    #[Before]
    #[After]
    public function resetTheStubs(): void
    {
        GuardRoutes::$middleware = ['auth'];
        GuardRoutes::$resources = [StageResource::class];
    }

    #[Test]
    public function it_counts_extension_authentication_in_rest_check(): void
    {
        config(['rest.extensions' => [GuardRoutes::class]]);
        GuardRoutes::$middleware = ['auth:sanctum'];
        GuardRoutes::$resources = [TripResource::class];
        $this->expose();

        $this->assertStringNotContainsString('is not behind an authentication middleware', $this->check());
    }

    #[Test]
    public function it_checks_the_routes_beside_an_invalid_entry_of_another_kind(): void
    {
        config(['rest.extensions' => [GuardRoutes::class, 'Missing\\Extension']]);
        GuardRoutes::$middleware = ['auth:sanctum'];
        GuardRoutes::$resources = [TripResource::class];
        $this->expose();

        $output = $this->check();

        $this->assertStringContainsString(rtrim(InvalidExtensionException::notAClass('Missing\\Extension')->getMessage(), '.'), $output);
        $this->assertStringNotContainsString('is not behind an authentication middleware', $output);
    }

    private function expose(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('trips', TripController::class)->register();
        });
    }

    private function check(): string
    {
        Artisan::call('rest:check', ['controllers' => [TripController::class]], $output = new BufferedOutput);

        return $this->summary($output->fetch());
    }
}

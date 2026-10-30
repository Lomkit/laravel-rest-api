<?php

namespace Lomkit\Rest\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Fixture;
use Orchestra\Testbench\Attributes\ResetRefreshDatabaseState;
use Orchestra\Testbench\TestCase;

#[ResetRefreshDatabaseState]
abstract class PackageTestCase extends TestCase
{
    use RefreshDatabase;

    /** @return list<Fixture> */
    protected function fixtures(): array
    {
        return [];
    }

    protected function getPackageProviders($app): array
    {
        return [RestServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('scout.driver', 'collection');
        $app['config']->set('rest.authorizations.strict', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach ($this->fixtures() as $fixture) {
            $this->loadMigrationsFrom($fixture->migrations());
        }
    }

    protected function defineRoutes($router): void
    {
        foreach ($this->fixtures() as $fixture) {
            $fixture->routes();
        }
    }
}

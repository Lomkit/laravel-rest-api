<?php

namespace Lomkit\Rest\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Fixture;
use Orchestra\Testbench\Attributes\ResetRefreshDatabaseState;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;

#[ResetRefreshDatabaseState]
#[WithConfig('scout.driver', 'collection')]
#[WithConfig('rest.authorizations.strict', true)]
abstract class PackageTestCase extends TestCase
{
    use RefreshDatabase;

    /** @return list<Fixture> */
    protected function fixtures(): array
    {
        return [];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [RestServiceProvider::class];
    }

    #[\Override]
    protected function defineDatabaseMigrations(): void
    {
        foreach ($this->fixtures() as $fixture) {
            $this->loadMigrationsFrom($fixture->migrations());
        }
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        foreach ($this->fixtures() as $fixture) {
            $fixture->routes();
        }
    }
}

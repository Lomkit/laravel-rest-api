<?php

namespace Lomkit\Rest\Tests;

use Illuminate\Database\Eloquent\Model;
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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventAccessingMissingAttributes();
    }

    #[\Override]
    protected function tearDown(): void
    {
        Model::preventAccessingMissingAttributes(false);

        parent::tearDown();
    }

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

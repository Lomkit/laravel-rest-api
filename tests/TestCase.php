<?php

namespace Lomkit\Rest\Tests;

use Illuminate\Config\Repository;
use Lomkit\Rest\RestServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;


    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/Support/Database/migrations');
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        tap($app->make('config'), function (Repository $config) {
            $config->set('auth.guards.api', [
                'driver' => 'token',
                'provider' => 'users',
            ]);
        });
    }

    /**
     * Define routes setup.
     *
     * @param  \Illuminate\Routing\Router  $router
     *
     * @return void
     */
    protected function defineRoutes($router)
    {
        include(__DIR__.'/Support/Routes/api.php');
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            RestServiceProvider::class,
        ];
    }
}
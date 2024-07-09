<?php

namespace Lomkit\Rest\Tests\Feature;

use Lomkit\Rest\Tests\Support\Database\Factories\UserFactory;
use Lomkit\Rest\Tests\Support\Traits\InteractsWithAuthorization;
use Lomkit\Rest\Tests\Support\Traits\InteractsWithResource;
use Lomkit\Rest\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use InteractsWithAuthorization;
    use InteractsWithResource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/Support/Database/migrations');
        $this->artisan('migrate', ['--database' => 'testing'])->run();
        $this->withAuthenticatedUser();
    }

    protected function resolveAuthFactoryClass()
    {
        return UserFactory::class;
    }

    protected function getPackageProviders($app)
    {
        return [
            \Lomkit\Rest\RestServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');

        $connection = env('DB_CONNECTION', 'sqlite');

        switch ($connection) {
            case 'mysql':
                $app['config']->set('database.connections.testing', [
                    'driver' => 'mysql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '3306'),
                    'database' => 'rest',
                    'username' => env('DB_USERNAME', 'root'),
                    'password' => env('DB_PASSWORD', ''),
                ]);
                break;
            case 'pgsql':
                $app['config']->set('database.connections.testing', [
                    'driver' => 'pgsql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '5432'),
                    'database' => 'rest',
                    'username' => env('DB_USERNAME', 'postgres'),
                    'password' => env('DB_PASSWORD', 'postgres'),
                ]);
                break;
            default:
                $app['config']->set('database.connections.testing', [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]);
        }
    }
}

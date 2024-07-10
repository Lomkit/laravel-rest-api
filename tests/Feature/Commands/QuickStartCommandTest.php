<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Lomkit\Rest\Tests\Feature\TestCase;

class QuickStartCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure api.php exists for tests
        if (! File::exists(base_path('routes/api.php'))) {
            File::put(base_path('routes/api.php'), '<?php');
        }
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    protected function cleanUp(): void
    {
        File::deleteDirectory(app_path('Rest'));
        File::deleteDirectory(app_path('Models'));
        File::delete(base_path('routes/api.php'));
    }

    public function test_quick_start_command_creates_necessary_files()
    {
        $this->artisan('rest:quick-start')->assertExitCode(0);

        $this->assertFileExists(app_path('Rest/Resources/UserResource.php'));
        $this->assertFileExists(app_path('Rest/Controllers/UsersController.php'));
    }

    public function test_quick_start_command_updates_api_routes()
    {
        $this->artisan('rest:quick-start')->assertExitCode(0);

        $routeContent = File::get(base_path('routes/api.php'));
        $this->assertStringContainsString(
            "\Lomkit\Rest\Facades\Rest::resource('users', \App\Rest\Controllers\UsersController::class);",
            $routeContent
        );
    }

    public function test_quick_start_command_does_not_duplicate_routes()
    {
        $this->artisan('rest:quick-start')->assertExitCode(0);
        $this->artisan('rest:quick-start')->assertExitCode(0);

        $routeContent = File::get(base_path('routes/api.php'));
        $count = substr_count($routeContent, "\Lomkit\Rest\Facades\Rest::resource('users', \App\Rest\Controllers\UsersController::class);");
        $this->assertEquals(1, $count, 'The route should only appear once in the file.');
    }

    public function test_quick_start_command_updates_user_model_namespace()
    {
        // Simulate the existence of App\Models\User
        File::makeDirectory(app_path('Models'), 0755, true);
        File::put(app_path('Models/User.php'), '<?php namespace App\Models; class User {}');

        $this->artisan('rest:quick-start')->assertExitCode(0);

        $this->assertFileExists(app_path('Rest/Resources/UserResource.php'));
        $this->assertFileExists(app_path('Rest/Controllers/UsersController.php'));

        $resourceContent = File::get(app_path('Rest/Resources/UserResource.php'));
        $controllerContent = File::get(app_path('Rest/Controllers/UsersController.php'));

        $this->assertStringContainsString('\App\Models\User::class', $resourceContent);

        $this->assertStringContainsString('\App\Rest\Resources\UserResource::class', $controllerContent);

        $this->assertStringContainsString('public static $model = \App\Models\User::class;', $resourceContent);
    }
}
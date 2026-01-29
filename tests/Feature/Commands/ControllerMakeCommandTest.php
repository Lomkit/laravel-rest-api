<?php

namespace Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class ControllerMakeCommandTest extends TestCase
{
    public function test_make_controller_command()
    {
        @unlink(app_path('Rest/Controllers/TestController.php'));

        $this
            ->artisan('rest:controller', ['name' => 'TestController'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Rest/Controllers/TestController.php'));
        $this->assertStringContainsString('class TestController extends Controller', file_get_contents(app_path('Rest/Controllers/TestController.php')));
        $this->assertStringContainsString('public static $resource = \Resource::class;', file_get_contents(app_path('Rest/Controllers/TestController.php')));

        unlink(app_path('Rest/Controllers/TestController.php'));
    }

    public function test_make_controller_command_with_resource()
    {
        @unlink(app_path('Rest/Controllers/TestController.php'));

        $this
            ->artisan('rest:controller', ['name' => 'TestController', '--resource' => 'TestResource'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Rest/Controllers/TestController.php'));
        $this->assertStringContainsString('class TestController extends Controller', file_get_contents(app_path('Rest/Controllers/TestController.php')));
        $this->assertStringContainsString('public static $resource = \App\Rest\Resources\TestResource::class;', file_get_contents(app_path('Rest/Controllers/TestController.php')));

        unlink(app_path('Rest/Controllers/TestController.php'));
    }
}

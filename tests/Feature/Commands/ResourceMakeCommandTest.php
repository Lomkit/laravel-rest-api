<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class ResourceMakeCommandTest extends TestCase
{
    public function test_make_resource_command()
    {
        @unlink(app_path('Rest/Resources/TestResource.php'));

        $this
            ->artisan('rest:resource', ['name' => 'TestResource'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Rest/Resources/TestResource.php'));
        $this->assertStringContainsString('class TestResource extends Resource', file_get_contents(app_path('Rest/Resources/TestResource.php')));
        $this->assertStringContainsString('public static $model = \Model::class;', file_get_contents(app_path('Rest/Resources/TestResource.php')));

        unlink(app_path('Rest/Resources/TestResource.php'));
    }

    public function test_make_resource_command_with_model()
    {
        @unlink(app_path('Rest/Resources/TestResource.php'));

        $this
            ->artisan('rest:resource', ['name' => 'TestResource', '--model' => 'Test'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Rest/Resources/TestResource.php'));
        $this->assertStringContainsString('class TestResource extends Resource', file_get_contents(app_path('Rest/Resources/TestResource.php')));
        $this->assertStringContainsString('public static $model = \App\Test::class;', file_get_contents(app_path('Rest/Resources/TestResource.php')));

        unlink(app_path('Rest/Resources/TestResource.php'));
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class BaseControllerMakeCommandTest extends TestCase
{
    public function test_make_base_controller_command()
    {
        @unlink(app_path('Rest/Controllers/Controller.php'));

        $this
            ->artisan('rest:base-controller', ['name' => 'Controller'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Rest/Controllers/Controller.php'));
        $this->assertStringContainsString('class Controller extends RestController', file_get_contents(app_path('Rest/Controllers/Controller.php')));

        unlink(app_path('Rest/Controllers/Controller.php'));
    }
}

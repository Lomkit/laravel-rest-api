<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class ControllerCommandTest extends TestCase
{
    public function test_create_controller_class(): void
    {
        $this->artisan('rest:controller', ['name' => 'TestController', '--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/TestController.php');
    }
}

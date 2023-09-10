<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class BaseControllerCommandTest extends TestCase
{
    public function test_create_base_controller_class(): void
    {
        $this->artisan('rest:base-controller', ['name' => 'TestBaseController', '--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/TestBaseController.php');
    }
}

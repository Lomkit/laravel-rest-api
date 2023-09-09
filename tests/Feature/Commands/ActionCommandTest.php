<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class ActionCommandTest extends TestCase
{
    public function test_create_action_class(): void
    {
        $this->artisan('rest:action', ['name' => 'TestAction', '--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/TestAction.php');
    }
}

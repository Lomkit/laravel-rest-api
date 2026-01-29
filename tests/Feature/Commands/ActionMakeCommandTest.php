<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class ActionMakeCommandTest extends TestCase
{
    public function test_make_action_command()
    {
        @unlink(app_path('Rest/Actions/TestAction.php'));

        $this
            ->artisan('rest:action', ['name' => 'TestAction'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Rest/Actions/TestAction.php'));
        $this->assertStringContainsString('class TestAction extends RestAction', file_get_contents(app_path('Rest/Actions/TestAction.php')));

        unlink(app_path('Rest/Actions/TestAction.php'));
    }
}

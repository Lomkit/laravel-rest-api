<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\CallRestApiAction;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;

class ActionCommandTest extends TestCase
{
    public function test_create_action_class(): void
    {
        $this->artisan('rest:action', ['name' => 'TestAction', '--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/TestAction.php');
    }
}
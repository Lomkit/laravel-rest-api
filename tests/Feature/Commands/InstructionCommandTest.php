<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class InstructionCommandTest extends TestCase
{
    public function test_create_instruction_class(): void
    {
        $this->artisan('rest:instruction', ['name' => 'TestInstruction', '--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/TestInstruction.php');
    }
}

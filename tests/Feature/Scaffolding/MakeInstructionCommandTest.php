<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Instructions\ScaffoldedInstruction;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;

class MakeInstructionCommandTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_writes_an_instruction_class_without_input_fields(): void
    {
        $this->artisan('rest:instruction', ['name' => 'ScaffoldedInstruction'])
            ->expectsOutputToContain('Instruction [app/Rest/Instructions/ScaffoldedInstruction.php] created successfully.')
            ->assertSuccessful();

        $this->loadGeneratedClass(ScaffoldedInstruction::class);

        $instruction = new ScaffoldedInstruction;

        $this->assertInstanceOf(Instruction::class, $instruction);
        $this->assertSame([], $instruction->fields(new RestRequest));
    }
}

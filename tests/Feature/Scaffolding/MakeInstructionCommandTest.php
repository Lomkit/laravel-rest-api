<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Instructions\ScaffoldedInstruction;
use Lomkit\Rest\Fields\Details\InputFieldDetails;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;

class MakeInstructionCommandTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_writes_an_instruction_class_that_declares_typed_fields(): void
    {
        $this->artisan('rest:instruction', ['name' => 'ScaffoldedInstruction'])->assertSuccessful();

        $path = app_path('Rest/Instructions/ScaffoldedInstruction.php');
        $this->assertFileExists($path);
        require_once $path;

        $instruction = new ScaffoldedInstruction;

        $this->assertInstanceOf(Instruction::class, $instruction);
        $this->assertSame(
            [['name' => 'id', 'type' => 'id', 'nullable' => false, 'default' => null, 'rules' => ['integer', 'required'], 'meta' => []]],
            InputFieldDetails::forFields($instruction->getFields(new RestRequest)),
        );
    }
}

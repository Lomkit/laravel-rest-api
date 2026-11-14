<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Instructions\Instruction;

class LabelledDeskInstruction extends Instruction
{
    public function __construct(public readonly string $label) {}
}

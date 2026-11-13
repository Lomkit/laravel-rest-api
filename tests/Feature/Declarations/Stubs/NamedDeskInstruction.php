<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Instructions\Instruction;

class NamedDeskInstruction extends Instruction
{
    #[\Override]
    public function name(): string
    {
        return 'Named On Build';
    }
}

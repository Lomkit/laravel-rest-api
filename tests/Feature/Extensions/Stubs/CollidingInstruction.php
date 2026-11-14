<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Instructions\Instruction;

class CollidingInstruction extends Instruction
{
    #[\Override]
    public function uriKey(): string
    {
        return 'arc.with-archived';
    }
}

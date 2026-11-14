<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Instructions\Instruction;

class EngineOnlyDeskInstruction extends Instruction
{
    public function __construct()
    {
        throw InvalidInstructionException::engineOnly(static::class);
    }
}

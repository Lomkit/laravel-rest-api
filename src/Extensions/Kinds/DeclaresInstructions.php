<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Instructions\Instruction;

interface DeclaresInstructions extends ExtensionKind
{
    /** @return list<Instruction|class-string<Instruction>> */
    public function instructions(InstructionsContext $context): array;
}

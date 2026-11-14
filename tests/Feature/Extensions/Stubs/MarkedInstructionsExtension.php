<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresInstructions;

final class MarkedInstructionsExtension extends Extension implements DeclaresInstructions
{
    #[\Override]
    public function instructions(InstructionsContext $context): array
    {
        return [MarkedInstruction::class];
    }
}

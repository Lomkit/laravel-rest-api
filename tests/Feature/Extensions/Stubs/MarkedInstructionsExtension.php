<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresInstructions;

class MarkedInstructionsExtension extends Extension implements DeclaresInstructions
{
    #[\Override]
    public static function name(): string
    {
        return 'zed';
    }

    #[\Override]
    public function instructions(InstructionsContext $context): array
    {
        return [MarkedInstruction::class];
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresInstructions;

final class GroupInstructionsExtension extends Extension implements DeclaresInstructions
{
    #[\Override]
    public static function name(): string
    {
        return 'gi';
    }

    #[\Override]
    public function instructions(InstructionsContext $context): array
    {
        return [EitherInstruction::class, ReaderGroupInstruction::class, EmptyGroupInstruction::class, ChosenReadersInstruction::class];
    }
}

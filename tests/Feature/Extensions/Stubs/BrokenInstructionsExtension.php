<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresInstructions;
use Lomkit\Rest\Instructions\Instruction;

class BrokenInstructionsExtension extends Extension implements DeclaresInstructions
{
    /** @var class-string<Instruction> */
    public static string $entry = Instruction::class;

    #[\Override]
    public static function name(): string
    {
        return 'broken';
    }

    #[\Override]
    public function instructions(InstructionsContext $context): array
    {
        return [self::$entry];
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresInstructions;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

final class ArchiveInstructionsExtension extends Extension implements DeclaresInstructions
{
    private static ?MarkedInstruction $shared = null;

    #[\Override]
    public static function name(): string
    {
        return 'arc';
    }

    #[\Override]
    public function instructions(InstructionsContext $context): array
    {
        $shared = self::$shared ??= new MarkedInstruction;

        return is_a($context->resource, AuthorResource::class, true) ? [WithArchivedInstruction::class, $shared] : [$shared];
    }
}

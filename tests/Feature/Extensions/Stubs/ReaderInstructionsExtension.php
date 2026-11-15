<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresInstructions;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;

final class ReaderInstructionsExtension extends Extension implements DeclaresInstructions
{
    #[\Override]
    public static function name(): string
    {
        return 'ri';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === ReaderVoucherResource::class;
    }

    #[\Override]
    public function instructions(InstructionsContext $context): array
    {
        return [ReaderInstruction::class];
    }
}

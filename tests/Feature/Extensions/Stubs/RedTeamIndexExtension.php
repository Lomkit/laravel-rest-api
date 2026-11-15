<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresIndexAttributes;
use Lomkit\Rest\Extensions\Kinds\DeclaresInstructions;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;

final class RedTeamIndexExtension extends Extension implements DeclaresIndexAttributes, DeclaresInstructions
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === VoucherResource::class;
    }

    #[\Override]
    public function indexAttributes(IndexAttributesContext $context): array
    {
        EngineLog::$calls[] = 'indexAttributes';
        EngineLog::$contexts[] = $context;

        return [Text::make('team')];
    }

    #[\Override]
    public function instructions(InstructionsContext $context): array
    {
        return [TeamInstruction::class];
    }
}

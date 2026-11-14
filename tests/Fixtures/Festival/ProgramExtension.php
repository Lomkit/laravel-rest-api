<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresInstructions;
use Lomkit\Rest\Extensions\Kinds\NarrowsLimits;

final class ProgramExtension extends Extension implements DeclaresInstructions, NarrowsLimits
{
    public static bool $instances = false;

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === ShowResource::class;
    }

    #[\Override]
    public function instructions(InstructionsContext $context): array
    {
        return self::$instances ? [OnlyHeadliners::make()] : [OnlyHeadliners::class];
    }

    #[\Override]
    public function narrowLimits(LimitsContext $context): void
    {
        if ($context->user !== null) {
            $context->allowSizes([10, 25]);
        }
    }
}

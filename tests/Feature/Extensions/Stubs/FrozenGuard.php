<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;

final class FrozenGuard extends Extension implements GuardsWrites
{
    #[\Override]
    public static function name(): string
    {
        return 'frozen';
    }

    #[\Override]
    public function guardWrite(WriteGuardContext $context): void
    {
        $context->refuse('The archive is frozen.');
    }
}

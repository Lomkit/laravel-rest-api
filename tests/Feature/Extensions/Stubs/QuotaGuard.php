<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;

final class QuotaGuard extends Extension implements GuardsWrites
{
    #[\Override]
    public function guardWrite(WriteGuardContext $context): void
    {
        $context->refuse('The monthly quota is reached.', 429);
    }
}

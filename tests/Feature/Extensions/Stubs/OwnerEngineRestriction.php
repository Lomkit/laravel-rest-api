<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;

final class OwnerEngineRestriction extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $context->query->whereIn('owner', [1]);
    }
}

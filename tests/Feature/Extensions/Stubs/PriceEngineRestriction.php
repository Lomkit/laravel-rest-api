<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;

final class PriceEngineRestriction extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $context->query->where('price', '>', 1)->whereNotIn('number', [5]);
    }
}

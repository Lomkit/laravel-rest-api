<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

final class SqlAwareEngineExtension extends Extension implements RestrictsEngineQueries, RestrictsQueries
{
    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->where($context->query->qualifyColumn('team'), 'red');
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        EngineLog::$contexts[] = $context;
    }
}

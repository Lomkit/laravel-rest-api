<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;

final class UnappliedEngineRestriction extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return false;
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        EngineLog::$calls[] = 'UnappliedEngineRestriction';

        $context->query->matchNothing();
    }
}

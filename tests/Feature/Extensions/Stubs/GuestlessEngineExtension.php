<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;

final class GuestlessEngineExtension extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        EngineLog::$calls[] = class_basename($context->resource);
        EngineLog::$users[] = $context->user;
        EngineLog::$contexts[] = $context;

        if ($context->user === null) {
            $context->query->matchNothing();
        }
    }
}

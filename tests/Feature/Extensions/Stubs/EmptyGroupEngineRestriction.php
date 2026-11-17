<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineConditionGroup;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;

final class EmptyGroupEngineRestriction extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'empty_group';
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $context->query->whereAny(fn (EngineConditionGroup $group): EngineConditionGroup => $group->whereIn('number', []));
    }
}

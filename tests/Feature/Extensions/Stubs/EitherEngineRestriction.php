<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineConditionGroup;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;

final class EitherEngineRestriction extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'either';
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        EngineLog::$contexts[] = $context;

        if (! $context->filtersWithGroups) {
            $context->query->matchNothing();

            return;
        }

        $context->query->whereAny(fn (EngineConditionGroup $group): EngineConditionGroup => $group
            ->where('number', '=', 5)
            ->whereIn('number', [4])
            ->whereAll(fn (EngineConditionGroup $all): EngineConditionGroup => $all->where('active', '=', true)->where('price', '>', 20)));
    }
}

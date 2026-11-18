<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Text\Engine\EngineConditionGroup;

final class OpenGroupEngineRestriction extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'open_group';
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $context->query->whereAny(fn (EngineConditionGroup $group): EngineConditionGroup => $group->where('number', '=', 5)->whereNotIn('number', []));
    }
}

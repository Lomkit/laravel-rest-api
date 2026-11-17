<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineConditionGroup;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;

final class ReaderGroupEngineRestriction extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'reader_group';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return is_a($resource, ReaderVoucherResource::class, true);
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $context->query->whereAny(fn (EngineConditionGroup $group): EngineConditionGroup => $group
            ->where('number', '=', 1)
            ->whereAll(fn (EngineConditionGroup $all): EngineConditionGroup => $all->where('price', '<', 5)->whereIn('readers', ['bob'])->whereNotIn('readers', ['cid'])));
    }
}

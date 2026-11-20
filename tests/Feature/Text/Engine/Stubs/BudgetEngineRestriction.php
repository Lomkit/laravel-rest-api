<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;

final class BudgetEngineRestriction extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'budget';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === RestrictedListedVoucherResource::class;
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $context->query->whereNotIn('code', ['V-b']);
    }
}

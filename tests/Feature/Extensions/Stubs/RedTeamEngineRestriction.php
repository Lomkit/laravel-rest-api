<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Tests\Fixtures\Ledger\TeamVoucherResource;

final class RedTeamEngineRestriction extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TeamVoucherResource::class;
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $context->query->whereIn('team', ['red']);
    }
}

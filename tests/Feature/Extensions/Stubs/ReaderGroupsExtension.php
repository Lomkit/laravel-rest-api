<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;

final class ReaderGroupsExtension extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === ReaderVoucherResource::class;
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $context->query->whereIn('readers', ['group-a']);
    }
}

<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;
use Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;

final class AtlasRowsExtension extends Extension implements RestrictsQueries
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return in_array($resource, [TripResource::class, JournalResource::class], true);
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->where($context->query->qualifyColumn('id'), '>', 0);
    }
}

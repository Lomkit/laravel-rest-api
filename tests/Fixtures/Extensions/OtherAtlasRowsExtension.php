<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;

final class OtherAtlasRowsExtension extends Extension implements RestrictsQueries
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TripResource::class;
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->where($context->query->qualifyColumn('id'), '>', 0);
    }
}

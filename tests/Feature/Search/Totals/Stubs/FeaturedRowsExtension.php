<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals\Stubs;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

class FeaturedRowsExtension extends Extension implements RestrictsQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'featured';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return is_a($resource, PerimeterShowResource::class, true);
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->where($context->query->qualifyColumn('featured'), false);
    }
}

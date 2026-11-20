<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

class ActiveRowsExtension extends Extension implements RestrictsQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'active';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return is_a($resource, PerimeterTotalledVoucherResource::class, true);
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->where($context->query->qualifyColumn('active'), true);
    }
}

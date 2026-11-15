<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

final class ElsewhereRestriction extends Extension implements RestrictsQueries
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return false;
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->whereRaw('1 = 0');
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

final class WideningExtension extends Extension implements RestrictsQueries
{
    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->orWhere($context->query->qualifyColumn('last_name'), '!=', 'Concealed');
    }
}

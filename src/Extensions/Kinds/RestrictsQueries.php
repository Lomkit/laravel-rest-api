<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;

interface RestrictsQueries extends ExtensionKind
{
    public function restrict(RestrictionContext $context): void;
}

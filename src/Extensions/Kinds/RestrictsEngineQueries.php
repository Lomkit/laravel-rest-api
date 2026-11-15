<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;

interface RestrictsEngineQueries extends ExtensionKind
{
    public function restrictEngine(EngineRestrictionContext $context): void;
}

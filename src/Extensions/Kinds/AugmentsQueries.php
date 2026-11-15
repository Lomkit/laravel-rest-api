<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\AugmentationContext;

interface AugmentsQueries extends ExtensionKind
{
    public function augment(AugmentationContext $context): void;
}

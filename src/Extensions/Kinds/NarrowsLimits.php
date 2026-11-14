<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\LimitsContext;

interface NarrowsLimits extends ExtensionKind
{
    public function narrowLimits(LimitsContext $context): void;
}

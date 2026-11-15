<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\NarrowsLimits;

class PlainLimits extends Extension implements NarrowsLimits
{
    #[\Override]
    public function narrowLimits(LimitsContext $context): void {}
}

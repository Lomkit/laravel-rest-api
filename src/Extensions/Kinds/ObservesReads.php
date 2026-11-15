<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\ReadObservationContext;

interface ObservesReads extends ExtensionKind
{
    public function observeRead(ReadObservationContext $context): void;
}

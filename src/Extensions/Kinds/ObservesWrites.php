<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;

interface ObservesWrites extends ExtensionKind
{
    public function observeWrite(WriteObservationContext $context): void;
}

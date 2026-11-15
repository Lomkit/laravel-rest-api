<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;

interface GuardsWrites extends ExtensionKind
{
    public function guardWrite(WriteGuardContext $context): void;
}

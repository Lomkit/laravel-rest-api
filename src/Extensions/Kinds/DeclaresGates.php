<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\GatesContext;

interface DeclaresGates extends ExtensionKind
{
    /** @return list<string> */
    public function gates(GatesContext $context): array;
}

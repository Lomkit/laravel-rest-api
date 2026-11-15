<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;

interface ChecksResources extends ExtensionKind
{
    public function checkResource(ResourceCheckContext $context): void;
}

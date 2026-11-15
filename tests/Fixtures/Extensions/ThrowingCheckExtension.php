<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use RuntimeException;

final class ThrowingCheckExtension extends Extension implements ChecksResources
{
    #[\Override]
    public function checkResource(ResourceCheckContext $context): void
    {
        $context->problem('the schema is read once.');

        throw new RuntimeException('The schema cannot be read.');
    }
}

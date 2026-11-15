<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;

final class ColumnCheckExtension extends Extension implements ChecksResources
{
    #[\Override]
    public function checkResource(ResourceCheckContext $context): void
    {
        ExtensionLog::$checked[] = $context->resource;

        if ($context->resource === TripResource::class) {
            $context->problem('the trips table has no tenant_id column.');
        }
    }
}

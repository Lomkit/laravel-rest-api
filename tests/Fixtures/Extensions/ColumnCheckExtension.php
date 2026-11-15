<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;

class ColumnCheckExtension extends Extension implements ChecksResources
{
    #[\Override]
    public static function name(): string
    {
        return 'columns';
    }

    #[\Override]
    public function checkResource(ResourceCheckContext $context): void
    {
        ExtensionLog::$checked[] = $context->resource::class;

        if ($context->resource instanceof TripResource) {
            $context->problem('the trips table has no tenant_id column.');
        }
    }
}

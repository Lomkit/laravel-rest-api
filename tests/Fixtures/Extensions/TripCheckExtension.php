<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;

final class TripCheckExtension extends Extension implements ChecksResources
{
    #[\Override]
    public static function name(): string
    {
        return 'trips';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TripResource::class;
    }

    #[\Override]
    public function checkResource(ResourceCheckContext $context): void
    {
        ExtensionLog::$checked[] = $context->resource;
    }
}

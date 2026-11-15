<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;
use RuntimeException;

final class UndecidedCheckExtension extends Extension implements ChecksResources
{
    #[\Override]
    public static function name(): string
    {
        return 'undecided';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        if ($resource === TripResource::class) {
            throw new RuntimeException('The config table is missing.');
        }

        return true;
    }

    #[\Override]
    public function checkResource(ResourceCheckContext $context): void
    {
        ExtensionLog::$checked[] = $context->resource;
    }
}

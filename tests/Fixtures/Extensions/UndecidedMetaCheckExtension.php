<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;
use RuntimeException;

final class UndecidedMetaCheckExtension extends Extension implements AddsSearchMeta, ChecksResources
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        if ($resource === TripResource::class) {
            throw new RuntimeException('The plan table is missing.');
        }

        return true;
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        return [];
    }

    #[\Override]
    public function checkResource(ResourceCheckContext $context): void
    {
        ExtensionLog::$checked[] = $context->resource;
    }
}

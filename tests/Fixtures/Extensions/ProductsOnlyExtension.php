<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;

final class ProductsOnlyExtension extends Extension implements NotesResources
{
    public function __construct()
    {
        ExtensionLog::$built[] = self::class;
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        ExtensionLog::$asked[] = self::class.' '.$resource;

        return $resource === ProductResource::class;
    }
}

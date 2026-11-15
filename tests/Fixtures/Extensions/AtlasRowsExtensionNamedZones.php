<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

final class AtlasRowsExtensionNamedZones extends AtlasRowsExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'zones';
    }
}

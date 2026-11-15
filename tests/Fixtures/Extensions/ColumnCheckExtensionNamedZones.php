<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

final class ColumnCheckExtensionNamedZones extends ColumnCheckExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'zones';
    }
}

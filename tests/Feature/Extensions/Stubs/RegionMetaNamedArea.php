<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class RegionMetaNamedArea extends RegionMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'area';
    }
}

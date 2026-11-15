<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class RegionSearchMetaNamedArea extends RegionSearchMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'area';
    }
}

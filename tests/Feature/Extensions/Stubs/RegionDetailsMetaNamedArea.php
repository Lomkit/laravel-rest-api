<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class RegionDetailsMetaNamedArea extends RegionDetailsMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'area';
    }
}

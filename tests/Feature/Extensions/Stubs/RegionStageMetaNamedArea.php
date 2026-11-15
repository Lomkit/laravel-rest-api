<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class RegionStageMetaNamedArea extends RegionStageMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'area';
    }
}

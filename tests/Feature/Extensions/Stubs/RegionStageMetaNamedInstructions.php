<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class RegionStageMetaNamedInstructions extends RegionStageMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'instructions';
    }
}

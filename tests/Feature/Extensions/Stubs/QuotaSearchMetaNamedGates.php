<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class QuotaSearchMetaNamedGates extends QuotaSearchMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'gates';
    }
}

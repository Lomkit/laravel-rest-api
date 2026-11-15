<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class QuotaGuardNamedArchive extends QuotaGuard
{
    #[\Override]
    public static function name(): string
    {
        return 'archive';
    }
}

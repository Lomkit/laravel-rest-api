<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class RivalQuotaResponsesNamedLimits extends RivalQuotaResponses
{
    #[\Override]
    public static function name(): string
    {
        return 'limits';
    }
}

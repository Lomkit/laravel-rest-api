<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class QuotaMetaNamedGates extends QuotaMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'gates';
    }
}

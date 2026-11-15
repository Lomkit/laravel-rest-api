<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

final class GuestlessExtension extends Extension implements RestrictsQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'guestless';
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        if ($context->user === null) {
            $context->query->whereRaw('0 = 1');
        }
    }
}

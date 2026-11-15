<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\NarrowsLimits;

final class FirstNarrowing extends Extension implements NarrowsLimits
{
    /** @var (Closure(LimitsContext): mixed)|null */
    public static ?Closure $narrow = null;

    #[\Override]
    public static function name(): string
    {
        return 'first';
    }

    #[\Override]
    public function narrowLimits(LimitsContext $context): void
    {
        if (self::$narrow !== null) {
            (self::$narrow)($context);
        }
    }
}

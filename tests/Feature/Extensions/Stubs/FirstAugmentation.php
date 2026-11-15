<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Extensions\Contexts\AugmentationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AugmentsQueries;

class FirstAugmentation extends Extension implements AugmentsQueries
{
    /** @var (Closure(AugmentationContext): mixed)|null */
    public static ?Closure $augment = null;

    public static ?string $named = null;

    #[\Override]
    public static function name(): string
    {
        return self::$named ?? 'first';
    }

    #[\Override]
    public function augment(AugmentationContext $context): void
    {
        if (self::$augment !== null) {
            (self::$augment)($context);
        }
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\NarrowsFields;

final class OtherMaskingFields extends Extension implements NarrowsFields
{
    /** @var (Closure(FieldsContext): mixed)|null */
    public static ?Closure $narrow = null;

    /** @var list<FieldsContext> */
    public static array $contexts = [];

    #[\Override]
    public function narrowFields(FieldsContext $context): void
    {
        self::$contexts[] = $context;

        if (self::$narrow !== null) {
            (self::$narrow)($context);
        }
    }

    public static function reset(): void
    {
        self::$narrow = null;
        self::$contexts = [];
    }
}

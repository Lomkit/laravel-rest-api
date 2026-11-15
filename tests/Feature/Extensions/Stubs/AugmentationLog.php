<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class AugmentationLog
{
    /** @var list<string> */
    public static array $aliases = [];

    /** @var list<string> */
    public static array $gates = [];

    public static function reset(): void
    {
        self::$aliases = [];
        self::$gates = [];
        FirstAugmentation::$augment = null;
        SecondAugmentation::$augment = null;
    }
}

<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

enum Reach: int
{
    case Local = 1;
    case National = 2;
    case Global = 3;

    public function minViews(): int
    {
        return match ($this) {
            self::Local => 10,
            self::National => 100,
            self::Global => 1000,
        };
    }
}

<?php

namespace Lomkit\Rest\Support;

final readonly class Translation
{
    /** @param array<string, string> $replace */
    public static function line(string $key, array $replace = []): string
    {
        $line = __($key, $replace);

        return is_string($line) ? $line : $key;
    }
}

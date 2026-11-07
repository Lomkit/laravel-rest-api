<?php

namespace Lomkit\Rest\Text\Engine;

final class LiteralValue
{
    private const string UNQUOTABLE = '/`|:\s*(?:\(|\[\s*\()|\*\s*\z|\A\s*".*"\s*\z/s';

    public static function accepts(int|float|string|bool $value): bool
    {
        if (! is_string($value)) {
            return true;
        }

        if (is_numeric($value) && str_contains($value, '+')) {
            return false;
        }

        return preg_match(self::UNQUOTABLE, $value) !== 1;
    }
}

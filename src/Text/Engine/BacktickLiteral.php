<?php

namespace Lomkit\Rest\Text\Engine;

final readonly class BacktickLiteral implements EngineLiteral
{
    #[\Override]
    public function of(int|float|string|bool $value): int|float|string|bool
    {
        if (! is_string($value)) {
            return $value;
        }

        return str_ends_with($value, '\\') ? "`{$value} `" : "`{$value}`";
    }
}

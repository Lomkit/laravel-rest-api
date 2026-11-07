<?php

namespace Lomkit\Rest\Text\Engine;

final readonly class PlainLiteral implements EngineLiteral
{
    #[\Override]
    public function of(int|float|string|bool $value): int|float|string|bool
    {
        return $value;
    }
}

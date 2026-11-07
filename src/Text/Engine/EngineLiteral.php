<?php

namespace Lomkit\Rest\Text\Engine;

interface EngineLiteral
{
    public function of(int|float|string|bool $value): int|float|string|bool;
}

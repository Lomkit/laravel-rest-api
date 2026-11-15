<?php

namespace Lomkit\Rest\Text\Engine;

final readonly class StringListAttribute
{
    private function __construct(public string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }
}

<?php

namespace Lomkit\Rest\Text\Engine;

/** @internal */
final readonly class RestrictingExtension
{
    /** @param class-string $class */
    public function __construct(
        public string $name,
        public string $class,
    ) {}
}

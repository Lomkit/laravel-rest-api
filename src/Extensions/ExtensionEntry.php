<?php

namespace Lomkit\Rest\Extensions;

final readonly class ExtensionEntry
{
    public function __construct(
        public string $name,
        public ?string $class,
        public ExtensionOrigin $origin,
        public bool $disabled,
        public ?string $replaces = null,
    ) {}
}

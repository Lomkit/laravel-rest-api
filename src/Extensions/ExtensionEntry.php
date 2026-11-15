<?php

namespace Lomkit\Rest\Extensions;

/** @internal */
final readonly class ExtensionEntry
{
    public function __construct(
        public string $class,
        public ExtensionOrigin $origin,
        public bool $disabled = false,
        public ?string $name = null,
    ) {}

    public function disable(): self
    {
        return new self($this->class, $this->origin, true);
    }

    public function named(string $name): self
    {
        return new self($this->class, $this->origin, $this->disabled, $name);
    }
}

<?php

namespace Lomkit\Rest\Extensions;

final readonly class ExtensionEntry
{
    /** @param list<string> $runtime */
    public function __construct(
        public string $name,
        public ?string $class,
        public ExtensionOrigin $origin,
        public bool $disabled,
        public array $runtime = [],
    ) {}

    public function conflicts(): bool
    {
        return $this->origin === ExtensionOrigin::Runtime && ! $this->disabled && count($this->runtime) > 1;
    }

    public function disablesNothing(): bool
    {
        return $this->disabled && $this->runtime === [];
    }
}

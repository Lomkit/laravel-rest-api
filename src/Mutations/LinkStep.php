<?php

namespace Lomkit\Rest\Mutations;

final readonly class LinkStep
{
    /** @param array{?string, mixed}|null $seed */
    public function __construct(
        public string $path,
        public LinkColumn $column,
        public string $child,
        public ?string $current,
        public string $owner,
        public bool $detaches,
        public LinkCheck $check,
        public ?array $seed = null,
        public bool $moves = true,
    ) {}

    public function overridden(): self
    {
        return new self($this->path, $this->column, $this->child, $this->current, $this->owner, $this->detaches, $this->check, $this->seed, false);
    }

    public function next(): ?string
    {
        return $this->detaches ? null : $this->owner;
    }
}

<?php

namespace Lomkit\Rest\Concerns;

trait HasMeta
{
    /** @var array<string, mixed> */
    private array $meta = [];

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): static
    {
        $this->meta = [...$this->meta, ...$meta];

        return $this;
    }

    /** @return array<string, mixed> */
    public function getMeta(): array
    {
        return $this->meta;
    }
}

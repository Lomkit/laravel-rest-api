<?php

namespace Lomkit\Rest\Metrics;

use JsonSerializable;

final readonly class TableRow implements JsonSerializable
{
    /** @param array<string, int|float|null> $values */
    public function __construct(
        public int|string|bool|null $key,
        public ?string $label,
        public array $values,
    ) {}

    public function labelled(?string $label): self
    {
        return new self($this->key, $label, $this->values);
    }

    /** @return array{key: int|string|bool|null, label: string|null, values: array<string, int|float|null>} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'values' => $this->values];
    }
}

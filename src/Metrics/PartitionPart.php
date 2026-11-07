<?php

namespace Lomkit\Rest\Metrics;

use JsonSerializable;

final readonly class PartitionPart implements JsonSerializable
{
    public function __construct(
        public int|string|bool|null $key,
        public ?string $label,
        public int|float|null $value,
    ) {}

    public function labelled(?string $label): self
    {
        return new self($this->key, $label, $this->value);
    }

    /** @return array{key: int|string|bool|null, label: string|null, value: int|float|null} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'value' => $this->value];
    }
}

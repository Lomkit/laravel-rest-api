<?php

namespace Lomkit\Rest\Metrics;

use JsonSerializable;

final readonly class PartitionPart implements JsonSerializable
{
    public function __construct(
        public int|string|bool|null $key,
        public ?string $label,
        public int|float|null $value,
        public bool $compared = false,
        public int|float|null $previous = null,
    ) {}

    public function labelled(?string $label): self
    {
        return new self($this->key, $label, $this->value, $this->compared, $this->previous);
    }

    /** @return array{key: int|string|bool|null, label: string|null, value: int|float|null, previous?: int|float|null} */
    #[\Override]
    public function jsonSerialize(): array
    {
        $part = ['key' => $this->key, 'label' => $this->label, 'value' => $this->value];

        return $this->compared ? [...$part, 'previous' => $this->previous] : $part;
    }
}

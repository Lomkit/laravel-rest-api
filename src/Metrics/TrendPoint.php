<?php

namespace Lomkit\Rest\Metrics;

use JsonSerializable;

final readonly class TrendPoint implements JsonSerializable
{
    public function __construct(
        public string $label,
        public int|float|null $value,
    ) {}

    /** @return array{label: string, value: int|float|null} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['label' => $this->label, 'value' => $this->value];
    }
}

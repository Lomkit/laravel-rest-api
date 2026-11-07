<?php

namespace Lomkit\Rest\Metrics;

use JsonSerializable;

final readonly class TrendSplit implements JsonSerializable
{
    /** @param list<TrendPoint> $series */
    public function __construct(
        public int|string|bool|null $key,
        public ?string $label,
        public array $series,
    ) {}

    public function labelled(?string $label): self
    {
        return new self($this->key, $label, $this->series);
    }

    /** @return array{key: int|string|bool|null, label: string|null, series: list<TrendPoint>} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'series' => $this->series];
    }
}

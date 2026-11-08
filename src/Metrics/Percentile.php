<?php

namespace Lomkit\Rest\Metrics;

final readonly class Percentile
{
    private function __construct(
        public string $value,
        public float $fraction,
    ) {}

    public static function median(): self
    {
        return new self('median', 0.5);
    }

    public static function of(float $fraction): self
    {
        return new self('percentile', $fraction);
    }

    public function valid(): bool
    {
        return $this->fraction >= 0.0 && $this->fraction <= 1.0;
    }
}

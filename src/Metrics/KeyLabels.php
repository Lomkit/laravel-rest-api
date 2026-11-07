<?php

namespace Lomkit\Rest\Metrics;

final readonly class KeyLabels
{
    /** @param array<int|string, string> $labels */
    public function __construct(private array $labels) {}

    public function for(int|string|bool|null $key, ?string $label): ?string
    {
        $key = is_bool($key) ? (int) $key : $key;

        return $key !== null && array_key_exists($key, $this->labels) ? $this->labels[$key] : $label;
    }
}

<?php

namespace Lomkit\Rest\Metrics;

use Lomkit\Rest\Exceptions\InvalidMetricException;

trait HasLimit
{
    public ?int $limit = null;

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function groupLimit(): int
    {
        return $this->limit ?? $this->resource->context()->limits()->sizes->groups;
    }

    protected function ensureValidLimit(): void
    {
        if ($this->limit !== null && $this->limit < 1) {
            throw InvalidMetricException::invalidLimit(static::class, $this->limit);
        }
    }
}

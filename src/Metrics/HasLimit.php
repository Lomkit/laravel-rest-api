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

    protected function ensureValidLimit(): void
    {
        if ($this->limit !== null && $this->limit < 1) {
            throw InvalidMetricException::invalidLimit(static::class, $this->limit);
        }
    }
}

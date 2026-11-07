<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Metrics\MetricResult;

final readonly class Values implements MetricResult
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values) {}

    /** @return array<string, mixed> */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->values;
    }
}

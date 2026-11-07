<?php

namespace Lomkit\Rest\Metrics;

use JsonSerializable;

interface MetricResult extends JsonSerializable
{
    /** @return array<string, mixed> */
    #[\Override]
    public function jsonSerialize(): array;
}

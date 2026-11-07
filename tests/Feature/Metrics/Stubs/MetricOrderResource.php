<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Fixtures\Sales\OrderResource;

class MetricOrderResource extends OrderResource
{
    /** @var list<Metric> */
    public static array $declared = [];

    #[\Override]
    public function metrics(): array
    {
        return self::$declared;
    }
}

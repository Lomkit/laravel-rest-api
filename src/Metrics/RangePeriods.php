<?php

namespace Lomkit\Rest\Metrics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

final readonly class RangePeriods
{
    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $split,
        public CarbonImmutable $to,
    ) {}

    public static function of(MetricRange $range): self
    {
        $to = Date::now()->toImmutable();
        $split = $to->subDays($range->value);

        return new self($split->subDays($range->value), $split, $to);
    }
}

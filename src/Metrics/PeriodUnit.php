<?php

namespace Lomkit\Rest\Metrics;

use Carbon\CarbonImmutable;

enum PeriodUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function start(CarbonImmutable $date, int $weekStartsOn): CarbonImmutable
    {
        return match ($this) {
            self::Day => $date->startOfDay(),
            self::Week => $date->startOfWeek($weekStartsOn),
            self::Month => $date->startOfMonth(),
        };
    }

    public function add(CarbonImmutable $date, int $count): CarbonImmutable
    {
        return match ($this) {
            self::Day => $date->addDays($count),
            self::Week => $date->addWeeks($count),
            self::Month => $date->addMonths($count),
        };
    }

    public function label(CarbonImmutable $date): string
    {
        return $date->format($this === self::Month ? 'Y-m' : 'Y-m-d');
    }
}

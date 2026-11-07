<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Lomkit\Rest\Metrics\PeriodUnit;

final readonly class MySqlPeriodGrammar extends PeriodGrammar
{
    #[\Override]
    public function label(string $date, PeriodUnit $unit): string
    {
        $shift = $this->daysToMonday();
        $days = $shift === 0 ? "weekday({$date})" : "mod(weekday({$date}) + {$shift}, 7)";

        return match ($unit) {
            PeriodUnit::Day => "date_format({$date}, '%Y-%m-%d')",
            PeriodUnit::Week => "date_format(date_sub({$date}, interval {$days} day), '%Y-%m-%d')",
            PeriodUnit::Month => "date_format({$date}, '%Y-%m')",
        };
    }

    #[\Override]
    public function shift(string $date, string $minutes): string
    {
        return "({$date} + interval ({$minutes}) minute)";
    }
}

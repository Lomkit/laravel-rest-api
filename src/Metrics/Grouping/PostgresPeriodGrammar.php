<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Lomkit\Rest\Metrics\PeriodUnit;

final readonly class PostgresPeriodGrammar extends PeriodGrammar
{
    #[\Override]
    public function label(string $date, PeriodUnit $unit): string
    {
        $shift = $this->daysToMonday();
        $week = $shift === 0 ? "date_trunc('week', {$date})" : "date_trunc('week', {$date} + interval '{$shift} days') - interval '{$shift} days'";

        return match ($unit) {
            PeriodUnit::Day => "to_char(date_trunc('day', {$date}), 'YYYY-MM-DD')",
            PeriodUnit::Week => "to_char({$week}, 'YYYY-MM-DD')",
            PeriodUnit::Month => "to_char(date_trunc('month', {$date}), 'YYYY-MM')",
        };
    }

    #[\Override]
    public function shift(string $date, string $minutes): string
    {
        return "({$date} + ({$minutes}) * interval '1 minute')";
    }
}

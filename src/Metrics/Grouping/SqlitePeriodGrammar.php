<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Lomkit\Rest\Metrics\PeriodUnit;

final readonly class SqlitePeriodGrammar extends PeriodGrammar
{
    #[\Override]
    public function label(string $date, PeriodUnit $unit): string
    {
        $lastDay = ($this->weekStartsOn + 6) % 7;

        return match ($unit) {
            PeriodUnit::Day => "strftime('%Y-%m-%d', {$date})",
            PeriodUnit::Week => "strftime('%Y-%m-%d', {$date}, 'weekday {$lastDay}', '-6 days')",
            PeriodUnit::Month => "strftime('%Y-%m', {$date})",
        };
    }

    #[\Override]
    public function shift(string $date, string $minutes): string
    {
        return "datetime({$date}, ({$minutes}) || ' minutes')";
    }
}

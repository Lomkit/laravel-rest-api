<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\PeriodUnit;

abstract readonly class PeriodGrammar
{
    public function __construct(
        protected int $weekStartsOn,
    ) {}

    abstract public function label(string $date, PeriodUnit $unit): string;

    public static function for(string $driver, int $weekStartsOn): self
    {
        return match ($driver) {
            'sqlite' => new SqlitePeriodGrammar($weekStartsOn),
            'mysql', 'mariadb' => new MySqlPeriodGrammar($weekStartsOn),
            'pgsql' => new PostgresPeriodGrammar($weekStartsOn),
            default => throw InvalidMetricException::unsupportedDriver($driver),
        };
    }

    protected function daysToMonday(): int
    {
        return (8 - $this->weekStartsOn) % 7;
    }
}

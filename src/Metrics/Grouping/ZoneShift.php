<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Grammar;

final readonly class ZoneShift
{
    /** @param list<array{string, int}> $steps */
    private function __construct(
        private array $steps,
        private int $minutes,
    ) {}

    public static function between(CarbonImmutable $from, CarbonImmutable $to, DateTimeZone $stored, Model $model): ?self
    {
        $zone = $from->getTimezone();
        $minutes = self::minutes($zone, $stored, $from->getTimestamp());
        $steps = [];

        foreach (self::transitions([$zone, $stored], $from->getTimestamp(), $to->getTimestamp()) as $instant) {
            $next = self::minutes($zone, $stored, $instant);

            if ($next !== $minutes) {
                $steps[] = [(string) $model->fromDateTime(CarbonImmutable::createFromTimestamp($instant, $stored)), $minutes];
                $minutes = $next;
            }
        }

        return $steps === [] && $minutes === 0 ? null : new self($steps, $minutes);
    }

    public function minutesOf(string $column, Grammar $grammar): string
    {
        if ($this->steps === []) {
            return (string) $this->minutes;
        }

        $cases = array_map(fn (array $step): string => "when {$column} < {$grammar->quoteString($step[0])} then {$step[1]}", $this->steps);

        return 'case '.implode(' ', $cases)." else {$this->minutes} end";
    }

    /**
     * @param  list<DateTimeZone>  $zones
     * @return list<int>
     */
    private static function transitions(array $zones, int $from, int $to): array
    {
        $instants = [];

        foreach ($zones as $zone) {
            foreach ($zone->getTransitions($from, $to) ?: [] as $transition) {
                $instants[] = $transition['ts'];
            }
        }

        $instants = array_values(array_unique(array_filter($instants, fn (int $instant): bool => $instant > $from)));
        sort($instants);

        return $instants;
    }

    private static function minutes(DateTimeZone $zone, DateTimeZone $stored, int $instant): int
    {
        $at = CarbonImmutable::createFromTimestamp($instant);

        return intdiv($zone->getOffset($at) - $stored->getOffset($at), 60);
    }
}

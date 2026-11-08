<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Metrics\Grouping\KeyFilter;
use Lomkit\Rest\Metrics\Grouping\PeriodLabel;

final readonly class MeasurePeriods
{
    public function __construct(
        private Measure $measure,
        private QueryBuilder $filtered,
        private PeriodLabel $label,
    ) {}

    public function empty(): int|float|null
    {
        return $this->measure->empty();
    }

    /** @return array<string, int|float|null> */
    public function series(): array
    {
        return self::byPeriod($this->periodic($this->filtered->clone(), null));
    }

    public function largest(MetricColumn $split, int $count): RankedGroups
    {
        return RankedGroups::largest($this->measure, $this->filtered, $split, $count);
    }

    /**
     * @param  list<int|string|bool|null>|null  $keys
     * @return list<array{period: string, key: int|string|bool|null, value: int|float|null}>
     */
    public function cells(MetricColumn $split, ?array $keys): array
    {
        $filtered = $this->filtered->clone();

        return $this->periodic($keys === null ? $filtered : KeyFilter::inside($filtered, $split, $keys), $split);
    }

    /**
     * @param  list<int|string|bool|null>  $kept
     * @return array<string, int|float|null>
     */
    public function others(MetricColumn $split, array $kept): array
    {
        return self::byPeriod($this->periodic(KeyFilter::outside($this->filtered->clone(), $split, $kept), null));
    }

    /** @return list<array{period: string, key: int|string|bool|null, value: int|float|null}> */
    private function periodic(QueryBuilder $filtered, ?MetricColumn $split): array
    {
        $partition = $split === null ? [$this->label] : [$this->label, $split->column];
        $base = $this->measure->rows($filtered, 'period', $partition);
        $base->addSelect($this->label->as('period'));

        if ($split !== null) {
            $base->addSelect("{$split->column} as split_key");
        }

        $this->measure->select($base, 'period');
        $base->groupBy($partition);

        return array_values(array_map(function (object $row) use ($split): array {
            $values = get_object_vars($row);
            $period = $values['period'] ?? null;

            return ['period' => is_string($period) ? $period : '', 'key' => $split?->key($values['split_key'] ?? null), 'value' => $this->measure->value($values, 'period')];
        }, $base->get()->all()));
    }

    /**
     * @param  list<array{period: string, key: int|string|bool|null, value: int|float|null}>  $cells
     * @return array<string, int|float|null>
     */
    private static function byPeriod(array $cells): array
    {
        return array_column($cells, 'value', 'period');
    }
}

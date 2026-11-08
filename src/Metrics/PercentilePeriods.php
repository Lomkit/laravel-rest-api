<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Metrics\Grouping\PeriodLabel;

final readonly class PercentilePeriods implements PeriodValues
{
    public function __construct(
        private PercentileQuery $query,
        private QueryBuilder $filtered,
        private PeriodLabel $label,
    ) {}

    #[\Override]
    public function empty(): null
    {
        return null;
    }

    #[\Override]
    public function series(): array
    {
        return self::byPeriod($this->query->periodic($this->filtered->clone(), $this->label, null));
    }

    #[\Override]
    public function cells(MetricColumn $split): array
    {
        return $this->query->periodic($this->filtered->clone(), $this->label, $split);
    }

    #[\Override]
    public function groups(MetricColumn $split): GroupValues
    {
        return new PercentileGroups($this->query, $this->filtered, $split);
    }

    #[\Override]
    public function others(MetricColumn $split, array $kept): array
    {
        return self::byPeriod($this->query->periodic(PercentileQuery::outside($this->filtered->clone(), $split, $kept), $this->label, null));
    }

    /**
     * @param  list<array{period: string, key: int|string|bool|null, value: float|null}>  $cells
     * @return array<string, float|null>
     */
    private static function byPeriod(array $cells): array
    {
        return array_column($cells, 'value', 'period');
    }
}

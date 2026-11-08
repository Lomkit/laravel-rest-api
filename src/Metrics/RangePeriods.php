<?php

namespace Lomkit\Rest\Metrics;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
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

    /** @param Builder<Model> $query */
    public function current(Builder $query, MetricColumn $date): QueryBuilder
    {
        return AggregateQuery::window(AggregateQuery::base($query), $query, $date, $this->split, $this->to);
    }

    /** @param Builder<Model> $query */
    public function previous(Builder $query, MetricColumn $date): QueryBuilder
    {
        return AggregateQuery::base($query)
            ->where($date->column, '>=', AggregateQuery::stored($query, $this->from))
            ->where($date->column, '<', AggregateQuery::stored($query, $this->split));
    }
}

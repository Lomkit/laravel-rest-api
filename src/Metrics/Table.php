<?php

namespace Lomkit\Rest\Metrics;

use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Fields\SortDirection;

abstract class Table extends Metric
{
    public const int MAX_LIMIT = 100;

    public int $limit = 10;

    #[\Override]
    public function type(): string
    {
        return 'table';
    }

    /** @param Builder<Model> $query */
    #[\Override]
    abstract public function calculate(Builder $query): TableResult;

    #[\Override]
    public function schema(): ?Type
    {
        return ResultSchemas::table();
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    #[\Override]
    public function ensureValid(): static
    {
        if ($this->limit < 1 || $this->limit > self::MAX_LIMIT) {
            throw InvalidMetricException::invalidRowLimit(static::class, $this->limit, self::MAX_LIMIT);
        }

        return parent::ensureValid();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, TableColumn>  $columns
     */
    public function rows(Builder $query, string $groupBy, array $columns, ?string $orderBy = null, SortDirection|string $direction = SortDirection::Desc, string $dateColumn = 'created_at'): TableResult
    {
        $group = MetricColumn::group($this, $query, 'rows', $groupBy);
        $aggregates = $this->aggregates($query, $columns);
        $orderBy ??= (string) array_key_first($aggregates);
        $direction = SortDirection::parse($direction);

        if (! array_key_exists($orderBy, $aggregates)) {
            throw InvalidMetricException::unknownOrder(static::class, $orderBy, array_keys($aggregates));
        }

        $base = AggregateQuery::base($query);
        $range = $this->range();

        if ($range !== null) {
            $now = Date::now()->toImmutable();
            AggregateQuery::window($base, $query, MetricColumn::date($this, $query, 'rows', $dateColumn), $now->subDays($range->value), $now);
        }

        return TableResult::of($columns, $group, (new TableQuery($group, $aggregates))->rows($base, $orderBy, $direction, $this->limit));
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, TableColumn>  $columns
     * @return array<string, Measure>
     */
    protected function aggregates(Builder $query, array $columns): array
    {
        if ($columns === []) {
            throw InvalidMetricException::withoutColumns(static::class);
        }

        $aggregates = [];

        foreach ($columns as $name => $column) {
            if (preg_match('/^[a-z][A-Za-z0-9_]*$/', (string) $name) !== 1) {
                throw InvalidMetricException::invalidColumn(static::class, (string) $name);
            }

            $aggregates[$name] = $column->function instanceof Percentile
                ? PercentileQuery::of($this, $query, 'rows', $column->function, (string) $column->field)
                : new AggregateQuery($column->function, $column->field === null ? null : MetricColumn::aggregated($this, $query, 'rows', $column->field));
        }

        return $aggregates;
    }
}

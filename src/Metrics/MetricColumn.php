<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;

final readonly class MetricColumn
{
    private function __construct(
        public Field $field,
        public string $column,
    ) {}

    /** @param Builder<Model> $query */
    public static function aggregated(Metric $metric, Builder $query, Aggregate $function, string $name): self
    {
        return self::of($metric, $query, $function, 'aggregates', $name, 'a number', fn (Field $field): bool => $field instanceof Number);
    }

    /** @param Builder<Model> $query */
    public static function date(Metric $metric, Builder $query, Aggregate $function, string $name): self
    {
        return self::of($metric, $query, $function, 'dates its range with', $name, 'a date or datetime', fn (Field $field): bool => $field instanceof Date);
    }

    public function integer(): bool
    {
        return $this->field instanceof Integer;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  callable(Field): bool  $fits
     */
    private static function of(Metric $metric, Builder $query, Aggregate $function, string $use, string $name, string $expected, callable $fits): self
    {
        $field = $metric->resource->definition()->fields()->find($name);

        if ($field === null || $field->isVirtual()) {
            throw InvalidMetricException::unknownField($metric::class, $function->value, $use, $name, $metric->resource::class);
        }

        if (! $fits($field)) {
            throw InvalidMetricException::wrongField($metric::class, $function->value, $use, $name, $field->type(), $expected);
        }

        return new self($field, $query->qualifyColumn($name));
    }
}

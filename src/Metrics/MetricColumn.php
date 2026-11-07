<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;

final readonly class MetricColumn
{
    private function __construct(
        public Field $field,
        public string $column,
    ) {}

    /** @param Builder<Model> $query */
    public static function aggregated(Metric $metric, Builder $query, string $helper, string $name): self
    {
        return self::of($metric, $query, $helper, 'aggregates', $name, 'a number', fn (Field $field): bool => $field instanceof Number);
    }

    /** @param Builder<Model> $query */
    public static function date(Metric $metric, Builder $query, string $helper, string $name): self
    {
        return self::of($metric, $query, $helper, 'dates its range with', $name, 'a date or datetime', fn (Field $field): bool => $field instanceof Date);
    }

    /** @param Builder<Model> $query */
    public static function period(Metric $metric, Builder $query, string $helper, string $name): self
    {
        return self::of($metric, $query, $helper, 'dates its periods with', $name, 'a date or datetime', fn (Field $field): bool => $field instanceof Date);
    }

    /** @param Builder<Model> $query */
    public static function group(Metric $metric, Builder $query, string $helper, string $name): self
    {
        return self::of($metric, $query, $helper, 'groups by', $name, 'a text, enum, integer or boolean', fn (Field $field): bool => $field instanceof Text || $field instanceof Enum || $field instanceof Integer || $field instanceof Boolean);
    }

    public function integer(): bool
    {
        return $this->field instanceof Integer;
    }

    public function key(mixed $stored): int|string|bool|null
    {
        if ($stored === null || ! is_scalar($stored)) {
            return null;
        }

        if ($this->field instanceof Enum) {
            $stored = is_int($stored) ? $stored : (string) $stored;

            return $this->field->enumClass()::tryFrom($stored)->value ?? $stored;
        }

        $key = $this->field->cast($stored);

        return is_int($key) || is_string($key) || is_bool($key) ? $key : null;
    }

    public function label(int|string|bool|null $key): ?string
    {
        return match (true) {
            $key === null => null,
            is_bool($key) => $key ? 'true' : 'false',
            $this->field instanceof Enum => $this->field->enumClass()::tryFrom($key)->name ?? (string) $key,
            default => (string) $key,
        };
    }

    /**
     * @param  Builder<Model>  $query
     * @param  callable(Field): bool  $fits
     */
    private static function of(Metric $metric, Builder $query, string $helper, string $use, string $name, string $expected, callable $fits): self
    {
        $field = $metric->resource->definition()->fields()->find($name);

        if ($field === null || $field->isVirtual()) {
            throw InvalidMetricException::unknownField($metric::class, $helper, $use, $name, $metric->resource::class);
        }

        if (! $fits($field)) {
            throw InvalidMetricException::wrongField($metric::class, $helper, $use, $name, $field->type(), $expected);
        }

        return new self($field, $query->qualifyColumn($name));
    }
}

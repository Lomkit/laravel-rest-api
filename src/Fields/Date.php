<?php

namespace Lomkit\Rest\Fields;

use DateTimeInterface;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Lomkit\Rest\Query\Expressions\DayOf;

class Date extends Field
{
    #[\Override]
    public function operators(): array
    {
        return ['=', '!=', '>', '>=', '<', '<=', 'between', 'not between'];
    }

    #[\Override]
    public function baseRules(): array
    {
        return ['date'];
    }

    #[\Override]
    public function type(): string
    {
        return 'date';
    }

    #[\Override]
    public function toSchema(): Type
    {
        return (new StringType)->format('date');
    }

    #[\Override]
    protected function castValue(mixed $value): mixed
    {
        if (! $value instanceof DateTimeInterface && ! is_scalar($value)) {
            return $value;
        }

        return $this->toDate($value)->toImmutable();
    }

    #[\Override]
    protected function filterColumn(Builder $query, string $column): Expression|string
    {
        return $this->comparesByDay($query) ? new DayOf($column) : $column;
    }

    #[\Override]
    protected function filterValue(Builder $query, mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->filterValue($query, $item), $value);
        }

        if (! $value instanceof DateTimeInterface && ! is_scalar($value)) {
            return $value;
        }

        $date = $this->toDate($value);

        return $this->comparesByDay($query) ? $date->toDateString() : $query->getModel()->fromDateTime($date);
    }

    protected function comparesByDay(Builder $query): bool
    {
        return $query->getModel()->getConnection()->getDriverName() === 'sqlite';
    }

    protected function normalize(Carbon $date): Carbon
    {
        return $date->startOfDay();
    }

    protected function toDate(DateTimeInterface|int|float|string|bool $value): Carbon
    {
        return $this->normalize($value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value));
    }
}

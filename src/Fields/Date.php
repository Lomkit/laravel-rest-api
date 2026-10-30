<?php

namespace Lomkit\Rest\Fields;

use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
    protected function castValue(mixed $value): mixed
    {
        if (! $value instanceof DateTimeInterface && ! is_scalar($value)) {
            return $value;
        }

        return $this->toDate($value)->toImmutable();
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

        return $query->getModel()->fromDateTime($this->toDate($value));
    }

    protected function normalize(Carbon $date): Carbon
    {
        return $date->startOfDay();
    }

    private function toDate(DateTimeInterface|int|float|string|bool $value): Carbon
    {
        return $this->normalize($value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value));
    }
}

<?php

namespace Lomkit\Rest\Fields;

use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Date extends Field
{
    public function operators(): array
    {
        return ['=', '!=', '>', '>=', '<', '<=', 'between', 'not between'];
    }

    public function baseRules(): array
    {
        return ['date'];
    }

    public function type(): string
    {
        return 'date';
    }

    protected function filterValue(Builder $query, mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->filterValue($query, $item), $value);
        }

        if ($value instanceof DateTimeInterface) {
            return $query->getModel()->fromDateTime($this->normalize(Carbon::instance($value)));
        }

        if (! is_scalar($value)) {
            return $value;
        }

        return $query->getModel()->fromDateTime($this->normalize(Carbon::parse((string) $value)));
    }

    protected function normalize(Carbon $date): Carbon
    {
        return $date->startOfDay();
    }
}

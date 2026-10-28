<?php

namespace Lomkit\Rest\Fields;

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

        if ($value === null) {
            return null;
        }

        return $query->getModel()->fromDateTime(is_string($value) ? Carbon::parse($value) : $value);
    }
}

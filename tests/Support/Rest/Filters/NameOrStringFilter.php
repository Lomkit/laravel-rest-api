<?php

namespace Lomkit\Rest\Tests\Support\Rest\Filters;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Filters\Filter;

class NameOrStringFilter extends Filter
{
    public function handle(Builder $query, string $operator, mixed $value): void
    {
        $query
            ->where('name', $operator, $value)
            ->orWhere('string', $operator, $value);
    }
}

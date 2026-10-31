<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Contracts\Filter;

class FullNameFilter implements Filter
{
    #[\Override]
    public function apply(Builder $query, string $operator, mixed $value): void
    {
        $query->where('first_name', $operator, $value)->orWhere('last_name', $operator, $value);
    }
}

<?php

namespace Lomkit\Rest\Filters;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Resourcable;

class Filter
{
    use Makeable;
    use Resourcable;

    /**
     * The key the filter answers to in the "field" of a search filter.
     *
     * @var string
     */
    public string $field;

    public function __construct(string $field)
    {
        $this->field = $field;
    }

    /**
     * Perform the filter on the given query.
     *
     * @param Builder $query
     * @param string  $operator
     * @param mixed   $value
     *
     * @return void
     */
    public function handle(Builder $query, string $operator, mixed $value): void
    {
        // ...
    }
}

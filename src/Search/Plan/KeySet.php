<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\Clause;

interface KeySet
{
    public function constrain(Builder $query): void;

    /** @return list<string> */
    public function columns(): array;

    /** @return list<Clause> */
    public function perimeter(Resource $resource): array;
}

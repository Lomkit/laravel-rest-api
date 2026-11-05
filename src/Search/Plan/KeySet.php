<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;

interface KeySet
{
    public function constrain(Builder $query): void;

    /** @return list<string> */
    public function columns(): array;
}

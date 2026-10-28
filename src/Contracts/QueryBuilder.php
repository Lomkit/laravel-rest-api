<?php

namespace Lomkit\Rest\Contracts;

use Laravel\Scout\Builder;

interface QueryBuilder
{
    /**
     * Build a "search" query for the given resource.
     *
     *
     * @return $this
     */
    public function search(array $parameters = []);

    /**
     * Convert the query builder to an Eloquent query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder|Builder
     */
    public function toBase();
}

<?php

namespace Lomkit\Rest\Contracts;

interface QueryBuilder
{
    /**
     * Build a "search" query for the given resource.
     *
     * @param array $parameters
     *
     * @return $this
     */
    public function search(array $parameters = []);

    /**
     * Convert the query builder to an Eloquent query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Laravel\Scout\Builder
     */
    public function toBase();
}

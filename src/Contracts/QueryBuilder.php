<?php

namespace Lomkit\Rest\Contracts;

use Lomkit\Rest\Http\Requests\RestRequest;

interface QueryBuilder
{
    /**
     * Build a "search" query for the given resource.
     *
     * @param array $parameters
     * @return $this
     */
    public function search(array $parameters = []);

    /**
     * Convert the query builder to an Eloquent query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function toBase();
}
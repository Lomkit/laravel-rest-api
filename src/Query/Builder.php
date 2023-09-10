<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Tappable;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Traits\PerformMutation;
use Lomkit\Rest\Query\Traits\PerformSearch;

class Builder implements QueryBuilder
{
    use Tappable;
    use Conditionable;
    use PerformSearch;
    use PerformMutation;

    /**
     * Construct a new query builder for a resource.
     *
     * @param resource $resource
     *
     * @return void
     */
    public function __construct(Resource $resource, \Illuminate\Database\Eloquent\Builder|Relation $query = null)
    {
        $this->resource = $resource;
        $this->queryBuilder = $query ?? $resource::newModel()->query();
    }

    /**
     * The query builder instance.
     *
     * @var resource
     */
    protected $resource;

    /**
     * The query builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder|null
     */
    protected $queryBuilder;

    public function newQueryBuilder($parameters)
    {
        return app()->make(QueryBuilder::class, $parameters);
    }

    /**
     * Convert the query builder to an Eloquent query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function toBase()
    {
        return $this->queryBuilder;
    }
}

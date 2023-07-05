<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Tappable;
use Lomkit\Rest\Concerns\Authorizable;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Traits\PerformMutation;
use Lomkit\Rest\Query\Traits\PerformSearch;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasOne;
use RuntimeException;

class Builder implements QueryBuilder
{
    use Tappable,
        Conditionable,
        PerformSearch,
        PerformMutation,
        Authorizable;

    /**
     * Construct a new query builder for a resource.
     *
     * @param  Resource  $resource
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
     * @var Resource
     */
    protected $resource;

    /**
     * The query builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder|null
     */
    protected $queryBuilder;

    public function newQueryBuilder($parameters) {
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
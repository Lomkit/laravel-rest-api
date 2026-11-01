<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Tappable;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Traits\PerformMutation;
use Lomkit\Rest\Query\Traits\PerformSearch;
use Lomkit\Rest\Query\Traits\SearchesKeys;

class Builder implements EloquentQueryBuilder
{
    use Conditionable;
    use PerformMutation;
    use PerformSearch;
    use SearchesKeys;
    use Tappable;

    /**
     * Construct a new query builder for a resource.
     *
     *
     * @return void
     */
    public function __construct(Resource $resource, \Illuminate\Database\Eloquent\Builder|Relation|null $query = null)
    {
        $this->resource = $resource;
        $this->queryBuilder = $query ?? $resource::newModel()->query();
    }

    protected Resource $resource;

    /**
     * Determine if default limit should be applied.
     */
    protected bool $disableDefaultLimit = false;

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
     * Set whether to disable applying the default query limit.
     *
     * When disabled, the query will not enforce any default limit on the number of results,
     * allowing retrieval of all matching records unless a custom limit is specified.
     *
     * @param  bool  $disable  True to disable the default limit, false to enable it.
     * @return $this Returns the current instance for chaining.
     */
    #[\Override]
    public function disableDefaultLimit(bool $disable = true)
    {
        $this->disableDefaultLimit = $disable;

        return $this;
    }

    /**
     * Convert the query builder to an Eloquent query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    #[\Override]
    public function toBase()
    {
        return $this->queryBuilder;
    }
}

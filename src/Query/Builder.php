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
     * Determine if security should be disabled in case we don't want it.
     *
     * @var bool
     */
    protected bool $disableSecurity = false;

    /**
     * Determine if default limit should be applied.
     *
     * @var bool
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
     * Sets the security flag for the query builder.
     *
     * Toggling this flag disables or enables security checks during query building.
     *
     * @param bool $disable Set to true to disable security checks (default), or false to enable them.
     * @return $this The current instance for method chaining.
     */
    public function disableSecurity($disable = true)
    {
        $this->disableSecurity = $disable;

        return $this;
    }

    /**
     * Set whether to disable applying the default query limit.
     *
     * When disabled, the query will not enforce any default limit on the number of results,
     * allowing retrieval of all matching records unless a custom limit is specified.
     *
     * @param bool $disable True to disable the default limit, false to enable it.
     * @return self Returns the current instance for chaining.
     */
    public function disableDefaultLimit($disable = true)
    {
        $this->disableDefaultLimit = $disable;

        return $this;
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

<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Tappable;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Traits\PerformMutation;
use Lomkit\Rest\Search\Plan\QueryPlan;

class Builder implements EloquentQueryBuilder
{
    use Conditionable;
    use PerformMutation;
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
     * The query builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder|null
     */
    protected $queryBuilder;

    /** @return \Illuminate\Database\Eloquent\Builder<Model> */
    #[\Override]
    public function search(QueryPlan $plan): \Illuminate\Database\Eloquent\Builder
    {
        $query = $this->toBase();
        $plan->apply($query);

        return $query;
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

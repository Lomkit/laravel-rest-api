<?php

namespace Lomkit\Rest\Query\Operators;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Resource;

class IncludeOperator implements Operator
{
    public function __construct(
        protected array $include,
    ) {
    }

    public function handle(Builder $query, Resource $resource): Builder
    {
        return $query->with($this->include['relation'], function (Relation $query) use ($resource) {
            $resource = $resource->relation($this->include['relation'])?->resource();

            $queryBuilder = app()->make(QueryBuilder::class, ['resource' => $resource, 'query' => $query]);

            return $queryBuilder->search($this->include);
        });
    }
}

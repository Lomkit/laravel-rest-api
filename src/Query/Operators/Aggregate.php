<?php

namespace Lomkit\Rest\Query\Operators;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Resource;

class Aggregate implements Operator
{

    public function __construct(
        protected string $relation,
        protected ?string $alias,
        protected string $type,
        protected string $field = '*',
        protected array $filters = []
    )
    {}

    public function handle(Builder $query, Resource $resource): Builder
    {
        $relation = $this->relation;

        if (!is_null($this->alias)) {
            $relation .= $this->getAliasQuery();
        }

        return $query->withAggregate([$relation => function (Builder $query) use ($resource, $aggregate) {
            $resource = $resource->relation($aggregate['relation'])?->resource();

            $queryBuilder = app()->make(QueryBuilder::class, ['resource' => $resource, 'query' => $query]);

            return $queryBuilder->search(['filters' => $this->filters]);
        }], $this->field, $this->type);
    }

    protected function getAliasQuery(): string
    {
        return ' as '.$this->alias;
    }
}
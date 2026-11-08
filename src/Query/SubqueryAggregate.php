<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Expressions\ExistsQuery;

final readonly class SubqueryAggregate
{
    public function __construct(
        private QueryBuilder $subquery,
        private AggregateFunction $function,
    ) {}

    /** @param Builder<Model> $query */
    public function select(Builder $query, string $name): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select([$query->qualifyColumn('*')]);
        }

        if ($this->function !== AggregateFunction::Exists) {
            $query->selectSub($this->subquery, $name);

            return;
        }

        $query->addSelect(new ExistsQuery($this->subquery, $name));
        $query->getQuery()->addBinding($this->subquery->getBindings(), 'select');
        $query->withCasts([$name => 'bool']);
    }

    /** @param Builder<Model> $query */
    public function order(Builder $query, SortDirection $direction): void
    {
        if ($this->function !== AggregateFunction::Exists) {
            $query->orderBy($this->subquery, $direction->value);

            return;
        }

        $query->orderBy(new ExistsQuery($this->subquery), $direction->value);
        $query->getQuery()->addBinding($this->subquery->getBindings(), 'order');
    }
}

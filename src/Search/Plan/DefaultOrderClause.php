<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\Clause;

final readonly class DefaultOrderClause implements Clause
{
    public function __construct(private Resource $resource) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        $model = match (true) {
            $query instanceof EloquentBuilder, $query instanceof Relation => $query->getModel(),
            default => throw new InvalidArgumentException('A default order applies to the Eloquent builder or relation of the searched resource, not to '.get_debug_type($query).'.'),
        };

        /** @var array<string, 'asc'|'desc'|\SortDirection> $orders */
        $orders = $this->resource->defaultOrderBy($this->resource->context()->request);

        foreach ($orders as $column => $order) {
            $query->orderBy($model->qualifyColumn($column), $order);
        }
    }
}

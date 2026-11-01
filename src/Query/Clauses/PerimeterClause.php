<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Resource;

final readonly class PerimeterClause implements Clause
{
    public function __construct(private Resource $resource) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $this->resource->searchQuery($this->resource->context()->request, $query);
        });
    }
}

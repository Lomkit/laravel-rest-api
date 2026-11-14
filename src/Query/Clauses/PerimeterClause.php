<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\ExtensionRestrictions;

final readonly class PerimeterClause implements Clause
{
    public function __construct(private Resource $resource) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        $restrictions = ExtensionRestrictions::of($this->resource);

        if ($restrictions === null) {
            $query->where(function (Builder $query): void {
                $this->resource->perimeterQuery($query);
            });

            return;
        }

        $query->where(function (EloquentBuilder $group) use ($restrictions): void {
            $group->where(function (Builder $query): void {
                $this->resource->perimeterQuery($query);
            });

            $restrictions->apply($group);
        });
    }
}

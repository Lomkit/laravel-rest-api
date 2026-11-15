<?php

namespace Lomkit\Rest\Query\Clauses;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\ExtensionRestrictions;

final readonly class PerimeterClause implements Clause
{
    /** @param (Closure(EloquentBuilder<Model>): mixed)|null $perimeter */
    public function __construct(
        private Resource $resource,
        private ?Closure $perimeter = null,
    ) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        $restrictions = ExtensionRestrictions::of($this->resource);

        if ($restrictions === null) {
            $query->where($this->perimeter(...));

            return;
        }

        $query->where(function (EloquentBuilder $group) use ($restrictions): void {
            $group->where($this->perimeter(...));

            $restrictions->apply($group);
        });
    }

    /** @param EloquentBuilder<Model> $query */
    private function perimeter(EloquentBuilder $query): void
    {
        if ($this->perimeter === null) {
            $this->resource->perimeterQuery($query);

            return;
        }

        ($this->perimeter)($query);
    }
}

<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\ExtensionRestrictions;

/** @internal */
final readonly class ExtensionRestrictionClause implements Clause
{
    private function __construct(private ExtensionRestrictions $restrictions) {}

    /** @return list<self> */
    public static function of(Resource $resource): array
    {
        $restrictions = ExtensionRestrictions::of($resource);

        return $restrictions === null ? [] : [new self($restrictions)];
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $query->where(function (EloquentBuilder $group): void {
            $this->restrictions->apply($group);
        });
    }
}

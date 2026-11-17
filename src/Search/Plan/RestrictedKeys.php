<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\ExtensionRestrictionClause;

/** @internal */
final readonly class RestrictedKeys implements KeySet
{
    public function __construct(private KeySet $keys) {}

    #[\Override]
    public function constrain(Builder $query): void
    {
        $this->keys->constrain($query);
    }

    #[\Override]
    public function columns(): array
    {
        return $this->keys->columns();
    }

    #[\Override]
    public function perimeter(Resource $resource): array
    {
        return [...$this->keys->perimeter($resource), ...ExtensionRestrictionClause::of($resource)];
    }
}

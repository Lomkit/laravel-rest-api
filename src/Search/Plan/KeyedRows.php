<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use InvalidArgumentException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Query\Clauses\TrashedClause;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\Engine\EngineKeys;

final readonly class KeyedRows implements Clause, RowSource
{
    public function __construct(private EngineKeys $keys) {}

    #[\Override]
    public function clauses(Resource $resource, SearchNode $search): array
    {
        $trashed = TrashedClause::of($search->trashed);

        return $trashed === null ? [$this] : [$this, $trashed];
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        if (! $query instanceof EloquentBuilder) {
            throw new InvalidArgumentException('Engine keys constrain the Eloquent builder of the searched resource, not '.get_debug_type($query).'.');
        }

        $this->keys->constrain($query);
    }
}

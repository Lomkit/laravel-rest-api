<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Query\Clauses\TrashedClause;
use Lomkit\Rest\Search\SearchNode;

final readonly class KeyedRows implements Clause, RowSource
{
    public function __construct(private KeySet $keys) {}

    #[\Override]
    public function clauses(Resource $resource, SearchNode $search, array $aggregates = []): array
    {
        $trashed = TrashedClause::of($search->trashed);

        return $trashed === null ? [$this] : [$this, $trashed];
    }

    #[\Override]
    public function columns(): array
    {
        return $this->keys->columns();
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $this->keys->constrain($query);
    }
}

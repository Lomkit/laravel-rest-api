<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class OuterQueryShape
{
    /** @param array<string, mixed> $parts */
    private function __construct(private array $parts) {}

    /** @param Builder<Model> $query */
    public static function of(Builder $query): self
    {
        $base = $query->getQuery();

        return new self([
            QueryPart::Where->name => count($base->wheres),
            QueryPart::Join->name => count($base->joins ?? []),
            QueryPart::Order->name => [count($base->orders ?? []), count($base->unionOrders ?? [])],
            QueryPart::Group->name => count($base->groups ?? []),
            QueryPart::Having->name => count($base->havings ?? []),
            QueryPart::Union->name => count($base->unions ?? []),
            QueryPart::Limit->name => [$base->limit, $base->unionLimit, $base->groupLimit],
            QueryPart::Offset->name => [$base->offset, $base->unionOffset],
            QueryPart::Lock->name => $base->lock,
            QueryPart::Distinct->name => $base->distinct,
            QueryPart::From->name => $base->from,
            QueryPart::Callback->name => count($base->beforeQueryCallbacks),
            QueryPart::RemovedScope->name => $query->removedScopes(),
        ]);
    }

    public function changedFrom(self $before): ?QueryPart
    {
        return array_find(QueryPart::cases(), fn (QueryPart $part): bool => ($this->parts[$part->name] ?? null) !== ($before->parts[$part->name] ?? null));
    }
}

<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\RelationFilterGroup;

final readonly class RelationGroupClause implements Clause
{
    public function __construct(
        private Relation $relation,
        private bool $exists,
        private FilterGroupClause $filters,
        private string $boolean = 'and',
    ) {}

    public static function of(RelationFilterGroup $group): self
    {
        return new self($group->relation, $group->exists, FilterGroupClause::of($group->filters), $group->boolean());
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $query->has($this->relation->relation, $this->exists ? '>=' : '<', 1, $this->boolean, $this->constrain(...));
    }

    /** @param EloquentBuilder<Model> $query */
    private function constrain(EloquentBuilder $query): void
    {
        $this->filters->apply($query);

        $query->where(function (EloquentBuilder $query): void {
            $this->relation->applyPerimeter($query);
        });
    }
}

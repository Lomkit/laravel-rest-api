<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Relations\Relation;

final readonly class RelationGroupClause implements Clause
{
    public function __construct(
        private Relation $relation,
        private bool $exists,
        private FilterGroupClause $filters,
        private string $boolean = 'and',
    ) {}

    /** @param array<array-key, mixed> $filter */
    public static function fromPayload(Resource $resource, array $filter, string $boolean): self
    {
        $name = is_string($filter['relation'] ?? null) ? $filter['relation'] : '';
        $relation = str_contains($name, '.') ? null : $resource->relation($name);

        if ($relation === null) {
            throw InvalidFieldException::unknownRelation($name);
        }

        $nested = $filter['nested'] ?? [];

        return new self(
            $relation,
            ($filter['exists'] ?? true) !== false,
            FilterGroupClause::fromPayload($relation->resource(), is_array($nested) ? $nested : []),
            $boolean,
        );
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $relation = $this->relation;

        if (! $relation instanceof MorphTo) {
            $query->has($relation->relation, $this->exists ? '>=' : '<', 1, $this->boolean, $this->constrain(...));

            return;
        }

        if ($this->exists) {
            $this->hasTarget($query, $relation, $this->boolean);

            return;
        }

        $query->where(function (Builder $query) use ($relation): void {
            $query
                ->whereNot(function (Builder $query) use ($relation): void {
                    $this->hasTarget($query, $relation, 'and');
                })
                ->orWhereMorphedTo($relation->relation, null);
        }, null, null, $this->boolean);
    }

    private function hasTarget(Builder $query, MorphTo $relation, string $boolean): void
    {
        $query->hasMorph($relation->relation, $relation->targets()->modelClasses(), '>=', 1, $boolean, $this->constrain(...));
    }

    /** @param EloquentBuilder<Model> $query */
    private function constrain(EloquentBuilder $query): void
    {
        $this->filters->apply($query);

        $query->where(function (EloquentBuilder $query): void {
            $this->relation->applySearchQuery($query);
        });
    }
}

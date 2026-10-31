<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Relations\Relation;

final readonly class RelationConditionClause implements Clause
{
    public function __construct(
        private FieldPath $path,
        private FilterOperator $operator,
        private mixed $value,
        private string $boolean = 'and',
    ) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        $this->through($query, $this->path->relations, $this->boolean);
    }

    /** @param list<Relation> $relations */
    private function through(Builder $query, array $relations, string $boolean): void
    {
        $relation = array_shift($relations) ?? throw InvalidFieldException::notFilterable($this->path->field->name);

        $constraint = function (EloquentBuilder $query) use ($relation, $relations): void {
            if ($relations === []) {
                $this->match($query, $relation);

                return;
            }

            $this->through($query, $relations, 'and');

            if ($relation instanceof MorphTo) {
                $this->perimeter($query, $relation);
            }
        };

        if ($relation instanceof MorphTo) {
            $query->hasMorph($relation->relation, $relation->targets()->modelClasses(), '>=', 1, $boolean, $constraint);

            return;
        }

        $query->has($relation->relation, '>=', 1, $boolean, $constraint);
    }

    /** @param EloquentBuilder<Model> $query */
    private function match(EloquentBuilder $query, Relation $relation): void
    {
        $this->path->field->filter($query, $this->operator, $this->value, 'and', $this->path->column($query->getModel()));

        $this->perimeter($query, $relation);
    }

    /** @param EloquentBuilder<Model> $query */
    private function perimeter(EloquentBuilder $query, Relation $relation): void
    {
        $query->where(function (EloquentBuilder $query) use ($relation): void {
            $relation->applySearchQuery($query);
        });
    }
}

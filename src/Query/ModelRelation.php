<?php

namespace Lomkit\Rest\Query;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use InvalidArgumentException;
use Lomkit\Rest\Relations\Relation;

final readonly class ModelRelation
{
    public function __construct(
        public string $name,
        public bool $textKeys = false,
    ) {}

    public static function of(Relation $relation): self
    {
        return new self($relation->relation, TextMorphKeys::of($relation));
    }

    /** @return EloquentRelation<Model, Model, mixed> */
    public function on(Model $parent): EloquentRelation
    {
        $relation = EloquentRelation::noConstraintsForRelation(fn (): mixed => $parent->{$this->name}());

        if (! $relation instanceof EloquentRelation) {
            throw new InvalidArgumentException($parent::class.'::'.$this->name.'() is not a relation.');
        }

        return $this->textKeys ? TextKeyRelations::of($relation) : $relation;
    }

    /** @param Closure(\Illuminate\Database\Eloquent\Builder<Model>): void $constraint */
    public function has(Builder $query, string $operator, string $boolean, Closure $constraint): void
    {
        $query = RelationAggregate::eloquent($query);

        $query->has($this->textKeys ? $this->on($query->getModel()) : $this->name, $operator, 1, $boolean, $constraint);
    }
}

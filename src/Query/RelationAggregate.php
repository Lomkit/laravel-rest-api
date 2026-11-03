<?php

namespace Lomkit\Rest\Query;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Query\Expressions\UuidColumn;

final readonly class RelationAggregate
{
    public function __construct(
        private string $relation,
        private AggregateFunction $function,
        private ?Field $field,
    ) {}

    /** @param Closure(EloquentBuilder<Model>): void $constraint */
    public function select(Builder $query, string $name, Closure $constraint): void
    {
        $query = self::eloquent($query);

        $query->withAggregate([$this->relation.' as '.$name => $constraint], $this->column($query), $this->function->value);
    }

    /** @param EloquentBuilder<Model> $query */
    private function column(EloquentBuilder $query): Expression|string
    {
        if ($this->field === null) {
            return '*';
        }

        if (! $this->field instanceof ID || $this->field->resolvedKeyType() !== 'uuid') {
            return $this->field->name;
        }

        return new UuidColumn($this->qualifiedColumn($query, $this->field->name));
    }

    /** @param EloquentBuilder<Model> $query */
    private function qualifiedColumn(EloquentBuilder $query, string $column): string
    {
        $relation = $this->relation($query);

        if ($query->getQuery()->from === $relation->getQuery()->getQuery()->from) {
            return $relation->getRelationCountHash(false).'.'.$column;
        }

        return $relation->getRelated()->qualifyColumn($column);
    }

    /**
     * @param  EloquentBuilder<Model>  $query
     * @return Relation<Model, Model, mixed>
     */
    private function relation(EloquentBuilder $query): Relation
    {
        $relation = Relation::noConstraintsForRelation(fn (): mixed => $query->getModel()->{$this->relation}());

        if (! $relation instanceof Relation) {
            throw new InvalidArgumentException($query->getModel()::class.'::'.$this->relation.'() is not a relation.');
        }

        return $relation;
    }

    /** @return EloquentBuilder<Model> */
    private static function eloquent(Builder $query): EloquentBuilder
    {
        return match (true) {
            $query instanceof EloquentBuilder => $query,
            $query instanceof Relation => $query->getQuery(),
            default => throw new InvalidArgumentException(get_debug_type($query).' is not an Eloquent builder.'),
        };
    }
}

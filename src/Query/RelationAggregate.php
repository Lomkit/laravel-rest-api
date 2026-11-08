<?php

namespace Lomkit\Rest\Query;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Expressions\AggregateColumn;
use Lomkit\Rest\Query\Expressions\ExistsQuery;
use Lomkit\Rest\Query\Expressions\TextColumn;

final readonly class RelationAggregate implements AggregateQuery
{
    public function __construct(
        private string $relation,
        private AggregateFunction $function,
        private ?Field $field,
    ) {}

    /** @param Closure(EloquentBuilder<Model>): void $constraint */
    #[\Override]
    public function select(Builder $query, string $name, Closure $constraint): void
    {
        $query = self::eloquent($query);

        $query->withAggregate([$this->relation.' as '.$name => $constraint], $this->column($query, false), $this->function->value);
    }

    /** @param Closure(EloquentBuilder<Model>): void $constraint */
    #[\Override]
    public function order(Builder $query, Closure $constraint, SortDirection $direction): void
    {
        $query = self::eloquent($query);
        $subquery = $this->subquery($query, $constraint);

        if ($this->function !== AggregateFunction::Exists) {
            $query->orderBy($subquery, $direction->value);

            return;
        }

        $query->orderBy(new ExistsQuery($subquery), $direction->value);
        $query->getQuery()->addBinding($subquery->getBindings(), 'order');
    }

    /**
     * @param  EloquentBuilder<Model>  $query
     * @param  Closure(EloquentBuilder<Model>): void  $constraint
     */
    private function subquery(EloquentBuilder $query, Closure $constraint): QueryBuilder
    {
        $column = new AggregateColumn($this->function, $this->column($query, true));
        $relation = $this->relation($query);
        $subquery = $relation
            ->getRelationExistenceQuery($relation->getRelated()->newQuery(), $query)
            ->select($column)
            ->where(function (EloquentBuilder $query) use ($constraint): void {
                $constraint($query);
            })
            ->mergeConstraintsFrom($relation->getQuery())
            ->toBase();

        $subquery->orders = null;
        $subquery->setBindings([], 'order');

        if (count($subquery->columns ?? []) > 1) {
            $subquery->columns = [$subquery->columns[0]];
            $subquery->bindings['select'] = [];
        }

        return $subquery;
    }

    /** @param EloquentBuilder<Model> $query */
    private function column(EloquentBuilder $query, bool $qualified): Expression|string
    {
        if ($this->field === null) {
            return '*';
        }

        $uuid = $this->field instanceof ID && $this->field->resolvedKeyType() === 'uuid';

        if (! $uuid && ! $qualified) {
            return $this->field->name;
        }

        $column = $this->qualifiedColumn($query, $this->field->name);

        return $uuid ? new TextColumn($column) : $column;
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
    public static function eloquent(Builder $query): EloquentBuilder
    {
        return match (true) {
            $query instanceof EloquentBuilder => $query,
            $query instanceof Relation => $query->getQuery(),
            default => throw new InvalidArgumentException(get_debug_type($query).' is not an Eloquent builder.'),
        };
    }
}

<?php

namespace Lomkit\Rest\Query;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Expressions\AggregateColumn;
use Lomkit\Rest\Query\Expressions\ExistsQuery;
use Lomkit\Rest\Query\Expressions\UuidColumn;
use Lomkit\Rest\Relations\Relation;

final readonly class NestedAggregate implements AggregateQuery
{
    private const string ROWS = '@rows';

    /** @param non-empty-list<Relation> $through */
    public function __construct(
        private array $through,
        private string $relation,
        private Model $related,
        private AggregateFunction $function,
        private ?Field $field,
    ) {}

    #[\Override]
    public function select(Builder $query, string $name, Closure $constraint): void
    {
        $query = RelationAggregate::eloquent($query);
        $subquery = $this->subquery($query, $constraint);

        if ($query->getQuery()->columns === null) {
            $query->select([$query->qualifyColumn('*')]);
        }

        if ($this->function !== AggregateFunction::Exists) {
            $query->selectSub($subquery, $name);

            return;
        }

        $query->addSelect(new ExistsQuery($subquery, $name));
        $query->getQuery()->addBinding($subquery->getBindings(), 'select');
        $query->withCasts([$name => 'bool']);
    }

    #[\Override]
    public function order(Builder $query, Closure $constraint, SortDirection $direction): void
    {
        $query = RelationAggregate::eloquent($query);
        $subquery = $this->subquery($query, $constraint);

        if ($this->function !== AggregateFunction::Exists) {
            $query->orderBy($subquery, $direction->value);

            return;
        }

        $query->orderBy(new ExistsQuery($subquery), $direction->value);
        $query->getQuery()->addBinding($subquery->getBindings(), 'order');
    }

    /**
     * @param  EloquentBuilder<Model>  $parent
     * @param  Closure(EloquentBuilder<Model>): void  $constraint
     */
    private function subquery(EloquentBuilder $parent, Closure $constraint): QueryBuilder
    {
        $first = $this->through[0];
        $relation = EloquentRelation::noConstraintsForRelation(fn (): mixed => $parent->getModel()->{$first->relation}());

        if (! $relation instanceof EloquentRelation) {
            throw new InvalidArgumentException($parent->getModel()::class.'::'.$first->relation.'() is not a relation.');
        }

        $level = $relation
            ->getRelationExistenceQuery($relation->getRelated()->newQueryWithoutRelationships(), $parent)
            ->mergeConstraintsFrom($relation->getQuery());

        $this->level($level, $first, array_slice($this->through, 1), $constraint);

        return $parent
            ->getQuery()
            ->newQuery()
            ->from($this->related->getTable().' as '.self::ROWS)
            ->select(new AggregateColumn($this->function, $this->column()))
            ->whereExists($level->toBase());
    }

    /**
     * @param  EloquentBuilder<Model>  $query
     * @param  list<Relation>  $below
     * @param  Closure(EloquentBuilder<Model>): void  $constraint
     */
    private function level(EloquentBuilder $query, Relation $middle, array $below, Closure $constraint): void
    {
        $query->where(function (EloquentBuilder $query) use ($middle): void {
            $middle->applySearchQuery($query);
        });

        $next = array_shift($below);

        if ($next === null) {
            $query->has($this->relation, '>=', 1, 'and', function (EloquentBuilder $query) use ($constraint): void {
                $this->leaf($query, $constraint);
            });

            return;
        }

        $query->has($next->relation, '>=', 1, 'and', function (EloquentBuilder $query) use ($next, $below, $constraint): void {
            $this->level($query, $next, $below, $constraint);
        });
    }

    /**
     * @param  EloquentBuilder<Model>  $query
     * @param  Closure(EloquentBuilder<Model>): void  $constraint
     */
    private function leaf(EloquentBuilder $query, Closure $constraint): void
    {
        $model = $query->getModel();

        $query
            ->whereColumn($model->getQualifiedKeyName(), '=', self::ROWS.'.'.$model->getKeyName())
            ->where(function (EloquentBuilder $query) use ($constraint): void {
                $constraint($query);
            });
    }

    private function column(): Expression|string
    {
        if ($this->field === null) {
            return '*';
        }

        $column = self::ROWS.'.'.$this->field->name;

        return $this->field instanceof ID && $this->field->resolvedKeyType() === 'uuid' ? new UuidColumn($column) : $column;
    }
}

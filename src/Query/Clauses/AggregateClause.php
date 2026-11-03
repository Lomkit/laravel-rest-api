<?php

namespace Lomkit\Rest\Query\Clauses;

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
use Lomkit\Rest\Search\AggregateKey;
use Lomkit\Rest\Search\AggregateNode;

final readonly class AggregateClause implements Clause
{
    private function __construct(
        private string $relation,
        private AggregateFunction $function,
        private ?Field $field,
        private AggregateKey $key,
        private Clause $constraint,
    ) {}

    public static function of(AggregateNode $node, Clause $constraint): self
    {
        return new self($node->relation, $node->function, $node->field, $node->key, $constraint);
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $query = self::eloquent($query);

        $query->withAggregate(
            [$this->relation.' as '.$this->key->value => function (EloquentBuilder $query): void {
                $this->constraint->apply($query);
            }],
            $this->column($query),
            $this->function->value,
        );
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
        $relation = Relation::noConstraints(fn (): Relation => $query->getModel()->{$this->relation}());

        if ($query->getQuery()->from === $relation->getQuery()->getQuery()->from) {
            return $relation->getRelationCountHash(false).'.'.$column;
        }

        return $relation->getRelated()->qualifyColumn($column);
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

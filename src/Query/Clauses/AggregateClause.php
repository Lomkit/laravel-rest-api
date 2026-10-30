<?php

namespace Lomkit\Rest\Query\Clauses;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Query\Expressions\UuidColumn;

final readonly class AggregateClause implements Clause
{
    /** @param (Closure(EloquentBuilder<Model>): mixed)|null $constraints */
    public function __construct(
        private string $relation,
        private AggregateFunction $function,
        private ?Field $field = null,
        private ?string $alias = null,
        private ?Closure $constraints = null,
    ) {}

    /**
     * @param  array{relation: string, type: string, field?: string|null, alias?: string|null}  $aggregate
     * @param  Closure(EloquentBuilder<Model>): mixed  $constraints
     */
    public static function fromAggregate(FieldSet $fields, array $aggregate, Closure $constraints): self
    {
        $function = AggregateFunction::from($aggregate['type']);
        $field = $function->needsField() ? $fields->get($aggregate['field'] ?? '') : null;

        return new self($aggregate['relation'], $function, $field, $aggregate['alias'] ?? null, $constraints);
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $query = self::eloquent($query);

        $query->withAggregate(
            [$this->relation.' as '.($this->alias ?? $this->defaultAlias()) => $this->constraints ?? fn (): null => null],
            $this->column($query),
            $this->function->value,
        );
    }

    private function defaultAlias(): string
    {
        $name = sprintf('%s %s %s', $this->relation, $this->function->value, strtolower($this->field->name ?? '*'));

        return Str::snake((string) preg_replace('/[^[:alnum:][:space:]_]/u', '', $name));
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

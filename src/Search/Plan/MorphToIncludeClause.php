<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Relations\MorphColumns;
use Lomkit\Rest\Relations\MorphTo;

final readonly class MorphToIncludeClause implements Clause
{
    /** @param array<string, QueryPlan> $branches */
    public function __construct(
        public string $relation,
        public ?string $alias,
        public MorphColumns $columns,
        public array $branches,
    ) {}

    public static function of(MorphTo $relation, ?string $alias, QueryPlan $plan): self
    {
        return new self($relation->relation, $alias, $relation->columns(), array_fill_keys($relation->targets()->types(), $plan));
    }

    public function name(): string
    {
        return $this->alias ?? $this->relation;
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $query->afterQuery($this->load(...));
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return Collection<int, Model>
     */
    private function load(Collection $models): Collection
    {
        $parents = $models->all();
        $holders = [];

        foreach ($parents as $index => $parent) {
            if ($this->branch($parent) === null) {
                $parent->setRelation($this->name(), null);

                continue;
            }

            $holders[$index] = $this->alias === null ? $parent : self::copy($parent);
        }

        if ($holders === []) {
            return $models;
        }

        $holders[array_key_first($holders)]
            ->newCollection(array_values($holders))
            ->load([$this->relation => $this->constrain(...)]);

        foreach ($this->alias === null ? [] : $holders as $index => $copy) {
            $parents[$index]->setRelation($this->name(), $copy->getRelation($this->relation));
        }

        return $models;
    }

    /** @param Relation<*, *, *> $relation */
    private function constrain(Relation $relation): void
    {
        if (! $relation instanceof EloquentMorphTo) {
            throw new LogicException($this->relation.' is not a MorphTo relation.');
        }

        $callbacks = [];

        foreach ($this->branches as $type => $plan) {
            $callbacks[Relation::getMorphedModel($type) ?? $type] = static function (Builder $query) use ($plan): void {
                $plan->apply($query);
            };
        }

        $relation->constrain($callbacks);
    }

    private function branch(Model $model): ?QueryPlan
    {
        $type = $model->getAttributes()[$this->columns->typeColumn] ?? null;

        if (! is_string($type) || $model->getAttribute($this->columns->keyColumn) === null) {
            return null;
        }

        return $this->branches[$type] ?? null;
    }

    private static function copy(Model $model): Model
    {
        return $model->newInstance([], true)
            ->setRawAttributes($model->getAttributes(), true);
    }
}

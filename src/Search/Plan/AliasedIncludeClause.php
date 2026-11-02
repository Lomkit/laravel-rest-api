<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;
use Lomkit\Rest\Query\Clauses\Clause;

final readonly class AliasedIncludeClause implements Clause
{
    public function __construct(
        public string $relation,
        public string $alias,
        public QueryPlan $plan,
    ) {}

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

        if ($parents === []) {
            return $models;
        }

        $model = reset($parents);
        $relation = Relation::noConstraints(fn (): mixed => $model->newInstance()->{$this->relation}());

        if (! $relation instanceof Relation) {
            throw new LogicException($model::class.'::'.$this->relation.'() does not return an Eloquent relation.');
        }

        $relation->addEagerConstraints($parents);
        $this->plan->apply($relation);

        $loadedBeforeAliasing = [];

        foreach ($parents as $index => $parent) {
            if ($parent->relationLoaded($this->relation)) {
                $loadedBeforeAliasing[$index] = $parent->getRelation($this->relation);
            }
        }

        $relation->match(
            $relation->initRelation($parents, $this->relation),
            $relation->getEager(),
            $this->relation
        );

        foreach ($parents as $index => $parent) {
            $parent->setRelation($this->alias, $parent->getRelation($this->relation));

            if (array_key_exists($index, $loadedBeforeAliasing)) {
                $parent->setRelation($this->relation, $loadedBeforeAliasing[$index]);

                continue;
            }

            $parent->unsetRelation($this->relation);
        }

        return $models;
    }
}

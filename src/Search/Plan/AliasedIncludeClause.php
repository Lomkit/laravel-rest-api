<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Query\ModelRelation;

final readonly class AliasedIncludeClause implements Clause
{
    public function __construct(
        public string $relation,
        public string $alias,
        public QueryPlan $plan,
        private bool $textKeys = false,
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
        $first = $models->first();

        if ($first === null) {
            return $models;
        }

        $parents = $models->all();
        $copies = array_map(self::copy(...), $parents);

        $this->loadOn($first->newCollection($copies));

        foreach ($parents as $index => $parent) {
            $parent->setRelation($this->alias, $copies[$index]->getRelation($this->relation));
        }

        return $models;
    }

    /** @param Collection<int, Model> $copies */
    private function loadOn(Collection $copies): void
    {
        if ($this->textKeys) {
            (new TextKeyLoad(new ModelRelation($this->relation, true), $this->plan))->load($copies);

            return;
        }

        $copies->load([$this->relation => function (Relation $relation): void {
            $this->plan->apply($relation);
        }]);
    }

    private static function copy(Model $model): Model
    {
        return $model->newInstance([], true)
            ->setRawAttributes($model->getAttributes(), true);
    }
}

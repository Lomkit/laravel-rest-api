<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Query\ModelRelation;

final readonly class TextKeyLoad
{
    public function __construct(
        private ModelRelation $relation,
        private QueryPlan $plan,
    ) {}

    /**
     * @param  Collection<int, Model>  $models
     * @return Collection<int, Model>
     */
    public function load(Collection $models): Collection
    {
        $first = $models->first();

        if ($first === null) {
            return $models;
        }

        $parents = $models->all();
        $relation = $this->relation->on($first->newInstance());
        $relation->addEagerConstraints($parents);
        $this->plan->apply($relation);
        $relation->match($relation->initRelation($parents, $this->relation->name), $relation->getEager(), $this->relation->name);

        return $models;
    }
}

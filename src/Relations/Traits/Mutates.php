<?php

namespace Lomkit\Rest\Relations\Traits;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Relations\Relation;

trait Mutates
{
    /**
     * Perform actions before mutating a relation.
     *
     * @param Model $model The Eloquent model.
     * @param Relation $relation The relation being mutated.
     * @param array $mutationRelations An array of mutation relations.
     */
    public function beforeMutating(Model $model, Relation $relation, array $mutationRelations) {}

    /**
     * Perform actions after mutating a relation.
     *
     * @param Model $model The Eloquent model.
     * @param Relation $relation The relation being mutated.
     * @param array $mutationRelations An array of mutation relations.
     */
    public function afterMutating(Model $model, Relation $relation, array $mutationRelations) {}
}
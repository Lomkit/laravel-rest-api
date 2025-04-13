<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Concerns\Relations\PerformsRelationOperations;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;
use Lomkit\Rest\Rules\ArrayWith;

class BelongsToMany extends Relation implements RelationResource
{
    use HasPivotFields;
    use HasMultipleResults;
    use PerformsRelationOperations;

    /**
     * Define validation rules for the BelongsToMany relation.
     *
     * @param resource $resource The resource associated with the relation.
     * @param string   $prefix   The prefix used for validation rules.
     *
     * @return array An array of validation rules.
     */
    public function rules(Resource $resource, string $prefix)
    {
        return array_merge(
            parent::rules($resource, $prefix),
            [
                $prefix.'.*.pivot' => [
                    'prohibited_if:'.$prefix.'.*.operation,detach',
                    new ArrayWith($this->getPivotFields()),
                ],
            ]
        );
    }

    /**
     * Perform actions after mutating the BelongsToMany relation.
     *
     * @param Model    $model             The Eloquent model.
     * @param Relation $relation          The relation being mutated.
     * @param array    $mutationRelations An array of mutation relations.
     */
    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        foreach ($mutationRelations[$relation->relation] as $mutationRelation) {
            if ($mutationRelation['operation'] === 'create') {
                $this->create($model, $relation, $mutationRelation);

                continue;
            }

            match ($mutationRelation['operation']) {
                'update' => $this->update($model, $relation, $mutationRelation),
                'attach' => $this->attach($model, $relation, $mutationRelation),
                'detach' => $this->detach($model, $relation, $mutationRelation),
                'toggle' => $this->toggle($model, $relation, $mutationRelation),
                'sync' => $this->sync(
                    $model,
                    $relation,
                    $mutationRelation,
                    withoutDetaching: $mutationRelation['operation'] === 'update' || !isset($mutationRelation['without_detaching']) || !$mutationRelation['without_detaching']
                ),
            };
        }
    }
}

<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;
use Lomkit\Rest\Rules\ArrayWith;

class BelongsToMany extends Relation implements RelationResource
{
    use HasPivotFields;
    use HasMultipleResults;

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
            if ($mutationRelation['operation'] === 'detach') {
                $model
                    ->{$relation->relation}()
                    ->detach(
                        app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
                            ->applyMutation($mutationRelation)
                            ->getKey()
                    );
            } elseif ($mutationRelation['operation'] === 'attach') {
                $model
                    ->{$relation->relation}()
                    ->attach(
                        [
                            app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
                                ->applyMutation($mutationRelation)
                                ->getKey() => $mutationRelation['pivot'] ?? [],
                        ]
                    );
            } elseif ($mutationRelation['operation'] === 'toggle') {
                $model
                    ->{$relation->relation}()
                    ->toggle(
                        [
                            app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
                                ->applyMutation($mutationRelation)
                                ->getKey() => $mutationRelation['pivot'] ?? [],
                        ]
                    );
            } elseif ($mutationRelation['operation'] === 'sync') {
                $model
                    ->{$relation->relation}()
                    ->sync(
                        [
                            app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
                                ->applyMutation($mutationRelation)
                                ->getKey() => $mutationRelation['pivot'] ?? [],
                        ],
                        !isset($mutationRelation['without_detaching']) || !$mutationRelation['without_detaching']
                    );
            } elseif (in_array($mutationRelation['operation'], ['create', 'update'])) {
                $model
                    ->{$relation->relation}()
                    ->syncWithoutDetaching(
                        [
                            app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
                                ->applyMutation($mutationRelation)
                                ->getKey() => $mutationRelation['pivot'] ?? [],
                        ]
                    );
            }
        }
    }
}

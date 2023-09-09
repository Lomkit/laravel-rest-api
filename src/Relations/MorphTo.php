<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Resource;

class MorphTo extends MorphRelation implements RelationResource
{
    /**
     * Create a new MorphTo instance.
     *
     * @param string $relation The name of the relation.
     * @param array $types An array of allowed types for the relation.
     */
    public function __construct($relation, array $types)
    {
        $this->relation = $relation;
        $this->types = $types;
    }

    /**
     * Perform actions before mutating the MorphTo relation.
     *
     * @param Model $model The Eloquent model.
     * @param Relation $relation The relation being mutated.
     * @param array $mutationRelations An array of mutation relations.
     */
    public function beforeMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        $model
            ->{$relation->relation}()
            ->{$mutationRelations[$relation->relation]['operation'] === 'detach' ? 'dissociate' : 'associate'}(
                app()->make(QueryBuilder::class, ['resource' => new $mutationRelations[$relation->relation]['type']])
                    ->applyMutation($mutationRelations[$relation->relation])
            );
    }

    /**
     * Define validation rules for the MorphTo relation.
     *
     * @param Resource $resource The resource associated with the relation.
     * @param string $prefix The prefix used for validation rules.
     * @return array An array of validation rules.
     */
    public function rules(Resource $resource, string $prefix)
    {
        return [
            ...parent::rules($resource, $prefix),
            $prefix.'.type' => [
                'required_with:'.$prefix,
                Rule::in(
                    $this->types
                )
            ]
        ];
    }
}
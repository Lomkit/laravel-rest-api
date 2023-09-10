<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Resource;

class MorphTo extends MorphRelation implements RelationResource
{
    public function __construct($relation, array $types)
    {
        $this->relation = $relation;
        $this->types = $types;
    }

    public function beforeMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        $model
            ->{$relation->relation}()
            ->{$mutationRelations[$relation->relation]['operation'] === 'detach' ? 'dissociate' : 'associate'}(
                app()->make(QueryBuilder::class, ['resource' => new $mutationRelations[$relation->relation]['type']()])
                    ->applyMutation($mutationRelations[$relation->relation])
            );
    }

    public function rules(Resource $resource, string $prefix)
    {
        return [
            ...parent::rules($resource, $prefix),
            $prefix.'.type' => [
                'required_with:'.$prefix,
                Rule::in(
                    $this->types
                ),
            ],
        ];
    }
}

<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Rules\Includable;

class MutateRequest extends RestRequest
{
    //@TODO: requiredOnCreation
    //@TODO: custom validation

    public function rules()
    {
        return $this->mutateRules($this->route()->controller::newResource());
    }
    public function mutateRules(Resource $resource, $prefix = 'mutate.*', $loadedRelations = [])
    {
        return array_merge(
            [
                $prefix.'.operation' => [
                    'required_with:'.$prefix,
                    Rule::in('create', 'update', ...($prefix === '' ? [] : ['attach', 'detach']))
                ],
                $prefix.'.attributes' => [
                    'prohibited_if:'.$prefix.'.operation,attach',
                    'prohibited_if:'.$prefix.'.operation,detach',
                    'array:'.Arr::join($resource->exposedFields($this), ',')
                ],
                $prefix.'.key' => [
                    'required_if:'.$prefix.'.operation,update',
                    'required_if:'.$prefix.'.operation,attach',
                    'required_if:'.$prefix.'.operation,detach',
                    'prohibited_if:'.$prefix.'.operation,create',
                    'exists:'.$resource::newModel()->getTable().','.$resource::newModel()->getKeyName()
                ]
            ],
            $this->relationRules($resource, $prefix.'.relations', $loadedRelations)
        );
    }

    protected function relationRules(Resource $resource, string $prefix = '', $loadedRelations = []) {
        $resourceRelationsNotLoaded = collect($resource->getRelations($this))
            ->filter(function($relation) use ($loadedRelations) {
                return !in_array($relation->relation, $loadedRelations);
            });

        $rules = [
            $prefix => [
                'array:'.Arr::join($resourceRelationsNotLoaded->map(function ($resourceRelationNotLoaded) {
                    return $resourceRelationNotLoaded->relation;
                })->toArray(), ',')
            ]
        ];

        foreach (
            $resourceRelationsNotLoaded as $relation
        ) {
            $prefixRelation = $prefix.'.'.$relation->relation;

            if ($relation instanceof BelongsToMany || $relation instanceof HasMany) {
                $prefixRelation .= '.*';
            }

            $rules = array_merge(
                $rules,
                $this->mutateRules($relation->resource(), $prefixRelation, array_merge($loadedRelations, [$relation->relation]))
            );
        }

        return $rules;
    }
}

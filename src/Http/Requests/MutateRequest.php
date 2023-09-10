<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\CustomRulable;

class MutateRequest extends RestRequest
{
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
                    Rule::in('create', 'update', ...($prefix === '' ? [] : ['attach', 'detach'])),
                ],
                $prefix.'.attributes' => [
                    'prohibited_if:'.$prefix.'.operation,attach',
                    'prohibited_if:'.$prefix.'.operation,detach',
                    'array:'.Arr::join($resource->fields($this), ','),
                ],
                $prefix.'.key' => [
                    'required_if:'.$prefix.'.operation,update',
                    'required_if:'.$prefix.'.operation,attach',
                    'required_if:'.$prefix.'.operation,detach',
                    'prohibited_if:'.$prefix.'.operation,create',
                    'exists:'.$resource::newModel()->getTable().','.$resource::newModel()->getKeyName(),
                ],
                $prefix => [
                    CustomRulable::make()->resource($resource),
                ],
            ],
            $this->relationRules($resource, $prefix.'.relations', $loadedRelations)
        );
    }

    protected function relationRules(Resource $resource, string $prefix = '', $loadedRelations = [])
    {
        $resourceRelationsNotLoaded = collect($resource->getRelations($this))
            ->filter(function ($relation) use ($loadedRelations) {
                return !in_array($relation->relation, $loadedRelations);
            });

        $rules = [
            $prefix => [
                'array:'.Arr::join($resourceRelationsNotLoaded->map(function ($resourceRelationNotLoaded) {
                    return $resourceRelationNotLoaded->relation;
                })->toArray(), ','),
            ],
        ];

        foreach (
            $resourceRelationsNotLoaded as $relation
        ) {
            $prefixRelation = $prefix.'.'.$relation->relation;

            if ($relation->hasMultipleEntries()) {
                $prefixRelation .= '.*';
            }

            $rules = array_merge_recursive(
                $rules,
                $relation->rules($resource, $prefix.'.'.$relation->relation),
                $this->mutateRules($relation->resource(), $prefixRelation, array_merge($loadedRelations, [$relation->relation]))
            );
        }

        return $rules;
    }
}

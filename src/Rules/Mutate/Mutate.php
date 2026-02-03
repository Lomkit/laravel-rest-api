<?php

namespace Lomkit\Rest\Rules\Mutate;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Rules\ArrayWithKey;
use Lomkit\Rest\Rules\Resource\ResourceCustomRules;
use Lomkit\Rest\Rules\Resource\ResourceMutateRelationOperation;
use Lomkit\Rest\Rules\RestRule;

class Mutate extends RestRule
{
    public function __construct(protected int $depth = 0, protected ?Relation $relation = null)
    {
    }

    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);

        $operationRules = Rule::in('create', 'update', ...($this->depth === 0 ? [] : ['attach', 'detach', 'toggle', 'sync']));

        $attributeConsideringRelationType = $attribute.($this->relation?->hasMultipleEntries() ? '.*' : '');

        return array_merge(
            [
                $attributeConsideringRelationType => [
                    (new ResourceMutateRelationOperation())->setResource($this->resource),
                    (new ResourceCustomRules())->setResource($this->resource),
                ],
                $attributeConsideringRelationType.'.operation' => [
                    'required',
                    $operationRules,
                ],
                $attributeConsideringRelationType.'.attributes' => [
                    'array',
                    'prohibited_if:'.$attributeConsideringRelationType.'.operation,attach',
                    'prohibited_if:'.$attributeConsideringRelationType.'.operation,detach',
                    new ArrayWithKey($this->resource->getFields($request)),
                ],
                $attributeConsideringRelationType.'.key' => [
                    'required_if:'.$attributeConsideringRelationType.'.operation,update',
                    'required_if:'.$attributeConsideringRelationType.'.operation,attach',
                    'required_if:'.$attributeConsideringRelationType.'.operation,detach',
                    'required_if:'.$attributeConsideringRelationType.'.operation,toggle',
                    'required_if:'.$attributeConsideringRelationType.'.operation,sync',
                    'prohibited_if:'.$attributeConsideringRelationType.'.operation,create',
                    'exists:'.$this->resource::newModel()->getTable().','.$this->resource::newModel()->getKeyName(),
                ],
                $attributeConsideringRelationType.'.without_detaching' => [
                    'boolean',
                    'prohibited_unless:'.$attributeConsideringRelationType.'.operation,sync',
                ],
                $attributeConsideringRelationType.'.relations' => [
                    'sometimes',
                    'array',
                    new ArrayWithKey(
                        collect($this->resource->getRelations($request))
                            ->map(function (Relation $relation) {
                                return $relation->relation;
                            })
                            ->toArray()
                    ),
                ],
                $attributeConsideringRelationType.'.relations.*' => (new MutateRelation(depth: $this->depth))->setResource($this->resource),
            ],
            $this->relation?->rules($this->resource, $attribute) ?? []
        );
    }
}

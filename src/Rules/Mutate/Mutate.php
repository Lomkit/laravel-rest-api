<?php

namespace Lomkit\Rest\Rules\Mutate;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Rules\ArrayWithKey;
use Lomkit\Rest\Rules\Resource\ResourceCustomRules;
use Lomkit\Rest\Rules\Resource\ResourceMutateRelationOperation;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Validation\RuleCompiler;

class Mutate extends RestRule
{
    public function __construct(protected int $depth = 0, protected ?Relation $relation = null) {}

    public function compile(RuleCompiler $into, string $attribute, mixed $value): void
    {
        $rules = $this->buildValidationRules($attribute, $value);

        if ($this->relation?->hasMultipleEntries() === true) {
            $into->addExpanded($rules);

            return;
        }

        $into->add($rules);
    }

    /** @return array<string, mixed> */
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;
        $model = $this->resource::newModel();
        $operations = $this->relation?->mutationOperations() ?? ['create', 'update'];

        return [
            $attribute => [
                (new ResourceMutateRelationOperation)->setResource($this->resource),
                (new ResourceCustomRules)->setResource($this->resource),
            ],
            $attribute.'.operation' => [
                'required',
                Rule::in($operations),
            ],
            $attribute.'.attributes' => [
                'array',
                'prohibited_if:'.$attribute.'.operation,attach',
                'prohibited_if:'.$attribute.'.operation,detach',
                new ArrayWithKey($this->resource->getFields($request)->names()),
            ],
            $attribute.'.key' => [
                'present_if:'.$attribute.'.operation,update',
                'present_if:'.$attribute.'.operation,attach',
                'present_if:'.$attribute.'.operation,detach',
                'present_if:'.$attribute.'.operation,toggle',
                'present_if:'.$attribute.'.operation,sync',
                'prohibited_if:'.$attribute.'.operation,create',
                Rule::exists($model::class, $model->getKeyName()),
            ],
            $attribute.'.without_detaching' => [
                'boolean',
                'prohibited_unless:'.$attribute.'.operation,sync',
            ],
            $attribute.'.relations' => [
                'sometimes',
                'array',
                new ArrayWithKey(array_column($this->resource->getRelations($request), 'relation')),
            ],
            $attribute.'.relations.*' => (new MutateRelation($this->depth))->setResource($this->resource),
        ];
    }
}

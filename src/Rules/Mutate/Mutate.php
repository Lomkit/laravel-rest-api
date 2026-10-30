<?php

namespace Lomkit\Rest\Rules\Mutate;

use Closure;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Rules\ArrayWithKey;
use Lomkit\Rest\Rules\Resource\ResourceCustomRules;
use Lomkit\Rest\Rules\Resource\ResourceMutateRelationOperation;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Validation\RuleCompiler;

class Mutate extends RestRule
{
    public function __construct(protected int $depth = 0, protected ?Relation $relation = null) {}

    #[\Override]
    public function compile(RuleCompiler $into, string $attribute, mixed $value): void
    {
        if (MutationOperation::tryFromEntry($value) === MutationOperation::Create) {
            $into->reach(Check::onClass($this->resource, 'create', $attribute));
        }

        $rules = $this->buildValidationRules($attribute, $value);

        if ($this->relation?->hasMultipleEntries() === true) {
            $into->addExpanded($rules);

            return;
        }

        $into->add($rules);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;
        $model = $this->resource::newModel();
        $operations = $this->relation?->mutationOperations() ?? [MutationOperation::Create, MutationOperation::Update];
        $write = MutationOperation::tryFromEntry($value)?->writeOperation();

        return [
            $attribute => [
                (new ResourceMutateRelationOperation)->setResource($this->resource),
                (new ResourceCustomRules)->setResource($this->resource),
            ],
            $attribute.'.operation' => [
                'required',
                Rule::enum(MutationOperation::class)->only($operations),
            ],
            $attribute.'.attributes' => [
                'array',
                ...self::whenOperation('prohibited_if', $attribute, fn (MutationOperation $operation): bool => $operation->writeOperation() === null),
                ...($write === null ? [] : [new WritableAttributes($this->resource->getFields($request), $write)]),
            ],
            $attribute.'.key' => [
                ...self::whenOperation('present_if', $attribute, fn (MutationOperation $operation): bool => $operation->requiresKey()),
                ...self::whenOperation('prohibited_if', $attribute, fn (MutationOperation $operation): bool => ! $operation->requiresKey()),
                Rule::exists($model::class, $model->getKeyName()),
            ],
            $attribute.'.without_detaching' => [
                'boolean',
                'prohibited_unless:'.$attribute.'.operation,'.MutationOperation::Sync->value,
            ],
            $attribute.'.relations' => [
                'sometimes',
                'array',
                new ArrayWithKey(array_column($this->resource->getRelations($request), 'relation')),
            ],
            $attribute.'.relations.*' => (new MutateRelation($this->depth))->setResource($this->resource),
        ];
    }

    /**
     * @param  Closure(MutationOperation): bool  $applies
     * @return list<string>
     */
    private static function whenOperation(string $rule, string $attribute, Closure $applies): array
    {
        return array_values(array_map(
            fn (MutationOperation $operation): string => $rule.':'.$attribute.'.operation,'.$operation->value,
            array_filter(MutationOperation::cases(), $applies),
        ));
    }
}

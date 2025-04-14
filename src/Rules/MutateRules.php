<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;

class MutateRules implements ValidationRule, ValidatorAwareRule
{
    /**
     * The validator instance.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;

    /**
     * The resource instance.
     *
     * @var resource
     */
    protected Resource $resource;

    /**
     * The request instance.
     *
     * @var RestRequest
     */
    protected RestRequest $request;

    /**
     * The relation instance.
     *
     * @var Relation|null
     */
    protected ?Relation $relation;

    /**
     * Specify if the validation is at root level.
     *
     * @var bool
     */
    protected bool $isRootValidation;

    public function __construct(\Lomkit\Rest\Http\Resource $resource, RestRequest $request, ?Relation $relation = null, bool $isRootValidation = false)
    {
        $this->resource = $resource;
        $this->request = $request;
        $this->relation = $relation;
        $this->isRootValidation = $isRootValidation;
    }

    /**
     * Set the current validator.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $initialAttribute = $attribute;

        if ($this->relation) {
            if ($this->relation->hasMultipleEntries()) {
                $attribute .= '.*';
            }
        }

        $this
            ->validator
            ->setRules(
                [
                    $attribute.'.operation' => [
                        'required_with:'.$attribute,
                        Rule::in('create', 'update', ...($this->isRootValidation ? [] : ['attach', 'detach', 'toggle', 'sync'])),
                    ],
                    $attribute.'.attributes' => [
                        'prohibited_if:'.$attribute.'.operation,attach',
                        'prohibited_if:'.$attribute.'.operation,detach',
                        new ArrayWith($this->resource->getFields($this->request)),
                    ],
                    $attribute.'.key' => [
                        fn(string $attribute, mixed $value, Closure $fail) =>
                            is_array($value) && $fail('The key field must not be an array.'),
                        'prohibits:'.$attribute.'.keys',
                        'prohibited_if:'.$attribute.'.operation,create',
                        'exists:'.$this->resource::newModel()->getTable().','.$this->resource::newModel()->getKeyName(),
                        new OperationDependentRequiredKey($attribute.'.keys'),
                    ],
                    $attribute.'.keys' => [
                        'array',
                        ...(!$this->relation?->hasMultipleEntries() ? ['prohibited'] : []),
                        'prohibits:'.$attribute.'.key',
                        'prohibited_if:'.$attribute.'.operation,create',
                        new OperationDependentRequiredKey($attribute.'.key'),
                    ],
                    $attribute.'.keys.*' => [
                        'exists:'.$this->resource::newModel()->getTable().','.$this->resource::newModel()->getKeyName(),
                    ],
                    $attribute.'.without_detaching' => [
                        'boolean',
                        'prohibited_unless:'.$attribute.'.operation,sync',
                    ],
                    $attribute.'.relations.*' => [
                        new MutateItemRelations($this->resource, $this->request),
                    ],
                    $attribute.'.relations' => [
                        new ArrayWith(
                            collect($this->resource->getRelations($this->request))
                                ->map(function (Relation $relation) {
                                    return $relation->relation;
                                })
                                ->toArray()
                        ),
                    ],
                ]
            )
            ->validate();

        // Relation rules
        $this
            ->validator
            ->setRules(
                $this->relation?->rules($this->resource, $initialAttribute) ?? []
            )
            ->validate();

        // Custom resource rules
        $this->validator
            ->setRules(
                [
                    $attribute => [
                        new CustomRulable($this->resource, $this->request),
                    ],
                ]
            )
            ->validate();

        $relations = $this->resource->getRelations($this->request);

        $this->validator
            ->setRules(
                collect($relations)
                    ->filter(function (Relation $relation) {
                        return
                            $relation->isRequiredOnCreation($this->request) ||
                            $relation->isProhibitedOnCreation($this->request) ||
                            $relation->isRequiredOnUpdate($this->request) ||
                            $relation->isProhibitedOnUpdate($this->request);
                    })
                    ->mapWithKeys(function (Relation $relation, $key) use ($attribute) {
                        return [$attribute.'.relations.'.$relation->relation => array_merge(
                            $relation->isRequiredOnCreation($this->request) ? [RequiredRelationOnCreation::make()->resource($this->resource)] : [],
                            $relation->isProhibitedOnCreation($this->request) ? [ProhibitedRelationOnCreation::make()->resource($this->resource)] : [],
                            $relation->isRequiredOnUpdate($this->request) ? [RequiredRelationOnUpdate::make()->resource($this->resource)] : [],
                            $relation->isProhibitedOnUpdate($this->request) ? [ProhibitedRelationOnUpdate::make()->resource($this->resource)] : [],
                        ),
                        ];
                    })
                    ->toArray()
            )
            ->validate();
    }
}

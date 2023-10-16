<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Http\Client\Request;
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
    protected Relation|null $relation;

    public function __construct(\Lomkit\Rest\Http\Resource $resource, RestRequest $request, Relation $relation = null)
    {
        $this->resource = $resource;
        $this->request = $request;
        $this->relation = $relation;
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
                        Rule::in('create', 'update', ...(preg_match('/^mutate\.\d$/', $attribute) ? [] : ['attach', 'detach'])),
                    ],
                    $attribute.'.attributes' => [
                        'prohibited_if:'.$attribute.'.operation,attach',
                        'prohibited_if:'.$attribute.'.operation,detach',
                        new ArrayWith($this->resource->getFields($this->request)),
                    ],
                    $attribute.'.key' => [
                        'required_if:'.$attribute.'.operation,update',
                        'required_if:'.$attribute.'.operation,attach',
                        'required_if:'.$attribute.'.operation,detach',
                        'prohibited_if:'.$attribute.'.operation,create',
                        'exists:'.$this->resource::newModel()->getTable().','.$this->resource::newModel()->getKeyName(),
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

        // Required relations on creation --> will be refactored soon to allow multiple requirements
        $this->validator
            ->setRules(
                collect($this->resource->getRelations($this->request))
                    ->filter(function (Relation $relation) {
                        return $relation->isRequiredOnCreation($this->request);
                    })
                    ->mapWithKeys(function (Relation $relation, $key) use ($attribute) {
                        return [$attribute.'.relations.'.$relation->relation => [
                            RequiredRelationOnCreation::make()->resource($this->resource),
                        ]];
                    })
                    ->toArray()
            )
            ->validate();
    }
}

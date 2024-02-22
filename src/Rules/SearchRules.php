<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Http\Client\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class SearchRules implements ValidationRule, ValidatorAwareRule
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
     * If the rules is specified at root level.
     *
     * @var bool
     */
    protected bool $isRootSearchRules;

    public function __construct(\Lomkit\Rest\Http\Resource $resource, RestRequest $request, bool $isRootSearchRules = true)
    {
        $this->resource = $resource;
        $this->request = $request;
        $this->isRootSearchRules = $isRootSearchRules;
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
        if ($attribute !== '') {
            $attribute .= '.';
        }

        $this
            ->validator
            ->setRules(
                array_merge(
                    [$attribute.'filters' => ['sometimes', 'array']],
                    $this->filtersRules($this->resource, $attribute.'filters'),
                    [$attribute.'scopes' => ['sometimes', 'array']],
                    $this->scopesRules($this->resource, $attribute.'scopes'),
                    [$attribute.'sorts' => ['sometimes', 'array']],
                    $this->sortsRules($this->resource, $attribute.'sorts'),
                    [$attribute.'selects' => ['sometimes', 'array']],
                    $this->selectsRules($this->resource, $attribute.'selects'),
                    [$attribute.'aggregates' => ['sometimes', 'array']],
                    $this->aggregatesRules($this->resource, $attribute.'aggregates'),
                    [$attribute.'instructions' => ['sometimes', 'array']],
                    $this->instructionsRules($this->resource, $attribute.'instructions'),
                    [
                        $attribute.'limit' => ['sometimes', 'integer', Rule::in($this->resource->getLimits($this->request))],
                        $attribute.'page'  => ['sometimes', 'integer'],
                        $attribute.'gates' => ['sometimes', 'array', Rule::in(['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'])],
                    ],
                    $this->isRootSearchRules ? [$attribute.'includes' => ['sometimes', 'array']] : [],
                    $this->isRootSearchRules ? $this->includesRules($this->resource, $attribute.'includes') : [],
                )
            )
            ->validate();
    }

    // @TODO: For now it's prohibited to have more than one nested depth, is this needed ?
    /**
     * Define the validation rules for filters within the search request.
     *
     * @param \Lomkit\Rest\Http\Resource $resource
     * @param string                     $prefix
     * @param bool                       $isMaxDepth
     *
     * @return array
     */
    public function filtersRules(\Lomkit\Rest\Http\Resource $resource, string $prefix, bool $isMaxDepth = false)
    {
        $rules = array_merge(
            [
                $prefix.'.*.field' => [
                    new IsNestedField($resource, $this->request),
                    "required_without:$prefix.*.nested",
                    'string',
                ],
                $prefix.'.*.operator' => [
                    Rule::in('=', '!=', '>', '>=', '<', '<=', 'like', 'not like', 'in', 'not in'),
                    'string',
                ],
                $prefix.'.*.value' => [
                    "exclude_if:$prefix.*.value,null",
                    "required_without:$prefix.*.nested",
                ],
                $prefix.'.*.type' => [
                    'sometimes',
                    Rule::in('or', 'and'),
                ],
                $prefix.'.*.nested' => !$isMaxDepth ? [
                    'sometimes',
                    "prohibits:$prefix.*.field,$prefix.*.operator,$prefix.*.value",
                    'prohibits:value',
                    'array',
                ] : [
                    'prohibited',
                ],
            ],
            !$isMaxDepth ? $this->filtersRules($resource, $prefix.'.*.nested', true) : []
        );

        return $rules;
    }

    /**
     * Define the validation rules for scopes within the search request.
     *
     * @param \Lomkit\Rest\Http\Resource $resource
     * @param string                     $prefix
     *
     * @return array
     */
    protected function scopesRules(\Lomkit\Rest\Http\Resource $resource, string $prefix)
    {
        $rules = [
            $prefix.'.*.name' => [
                Rule::in($resource->getScopes($this->request)),
                'required',
                'string',
            ],
            $prefix.'.*.parameters' => [
                'sometimes',
                'array',
            ],
        ];

        return $rules;
    }

    /**
     * Define the validation rules for instructions within the search request.
     *
     * @param \Lomkit\Rest\Http\Resource $resource
     * @param string                     $prefix
     *
     * @return array
     */
    protected function instructionsRules(\Lomkit\Rest\Http\Resource $resource, string $prefix)
    {
        $rules = [
            $prefix.'.*.name' => [
                Rule::in(
                    collect($resource->getInstructions($this->request))
                        ->map(function ($instruction) { return $instruction->uriKey(); })
                        ->toArray()
                ),
                'required',
                'string',
            ],
            $prefix.'.*.fields' => [
                'sometimes',
                'array',
            ],
            $prefix.'.*' => [
                Instruction::make()
                    ->resource($resource),
            ],
        ];

        return $rules;
    }

    /**
     * Define the validation rules for sorting options within the search request.
     *
     * @param \Lomkit\Rest\Http\Resource $resource
     * @param string                     $prefix
     *
     * @return array
     */
    protected function sortsRules(\Lomkit\Rest\Http\Resource $resource, string $prefix)
    {
        $rules = [
            $prefix.'.*.field' => [
                Rule::in($resource->getFields($this->request)),
                'required',
                'string',
            ],
            $prefix.'.*.direction' => [
                'sometimes',
                Rule::in('desc', 'asc'),
            ],
        ];

        return $rules;
    }

    /**
     * Define the validation rules for selecting fields within the search request.
     *
     * @param \Lomkit\Rest\Http\Resource $resource
     * @param string                     $prefix
     *
     * @return array
     */
    protected function selectsRules(\Lomkit\Rest\Http\Resource $resource, string $prefix)
    {
        $rules = [
            $prefix.'.*.field' => [
                Rule::in($resource->getFields($this->request)),
                'required',
                'string',
            ],
        ];

        return $rules;
    }

    /**
     * Define the validation rules for including related resources within the search request.
     *
     * @param \Lomkit\Rest\Http\Resource $resource
     * @param string                     $prefix
     *
     * @throws BindingResolutionException
     *
     * @return array
     */
    protected function includesRules(\Lomkit\Rest\Http\Resource $resource, string $prefix)
    {
        return [
            $prefix.'.*.relation' => [
                'required',
                NestedRelation::make($resource),
            ],
            $prefix.'.*.includes' => [
                'prohibited',
            ],
            $prefix.'.*' => [
                Includable::make($resource),
            ],
        ];
    }

    /**
     * Define the validation rules for aggregate functions within the search request.
     *
     * @param \Lomkit\Rest\Http\Resource $resource
     * @param string                     $prefix
     *
     * @return array
     */
    protected function aggregatesRules(\Lomkit\Rest\Http\Resource $resource, string $prefix)
    {
        return [
            $prefix.'.*.relation' => [
                'required',
                NestedRelation::make($resource),
            ],
            $prefix.'.*.type' => [
                Rule::in(['count', 'min', 'max', 'avg', 'sum', 'exists']),
            ],
            $prefix.'.*.field' => [
                'required_if:'.$prefix.'.*.type,min,max,avg,sum',
                'prohibited_if:'.$prefix.'.*.type,count,exists',
            ],
            $prefix.'.*' => [
                AggregateField::make()
                    ->resource($resource),
                AggregateFilterable::make()
                    ->resource($resource),
            ],
        ];
    }
}

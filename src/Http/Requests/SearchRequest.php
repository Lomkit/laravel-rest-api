<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\AggregateField;
use Lomkit\Rest\Rules\AggregateFilterable;
use Lomkit\Rest\Rules\Includable;
use Lomkit\Rest\Rules\Instruction;

class SearchRequest extends RestRequest
{

    /**
     * Define the validation rules for the search request.
     *
     * @return array
     */
    public function rules()
    {
        return $this->searchRules($this->route()->controller::newResource());
    }

    /**
     * Define the validation rules for filters within the search request.
     *
     * @param Resource $resource
     * @param string $prefix
     * @param bool $isMaxDepth
     * @return array
     */
    public function searchRules(Resource $resource, $prefix = '', $isRootSearchRules = true)
    {
        if ($prefix !== '') {
            $prefix .= '.';
        }

        return array_merge(
            [$prefix.'filters' => ['sometimes', 'array']],
            $this->filtersRules($resource, $prefix.'filters'),
            [$prefix.'scopes' => ['sometimes', 'array']],
            $this->scopesRules($resource, $prefix.'scopes'),
            [$prefix.'sorts' => ['sometimes', 'array']],
            $this->sortsRules($resource, $prefix.'sorts'),
            [$prefix.'selects' => ['sometimes', 'array']],
            $this->selectsRules($resource, $prefix.'selects'),
            [$prefix.'aggregates' => ['sometimes', 'array']],
            $this->aggregatesRules($resource, $prefix.'aggregates'),
            [$prefix.'instructions' => ['sometimes', 'array']],
            $this->instructionsRules($resource, $prefix.'instructions'),
            [
                'limit' => ['sometimes', 'integer', Rule::in($resource->limits($this))],
                'page' => ['sometimes', 'integer']
            ],
            $isRootSearchRules ? ['includes' => ['sometimes', 'array']] : [],
            $isRootSearchRules ? $this->includesRules($resource) : [],
        );
    }

    /**
     * Define the validation rules for filters within the search request.
     *
     * @param Resource $resource
     * @param string $prefix
     * @param bool $isMaxDepth
     * @return array
     */
    // @TODO: For now it's prohibited to have more than one nested depth, is this needed ?
    public function filtersRules(Resource $resource, string $prefix, $isMaxDepth = false) {
        $rules = array_merge(
            [
                $prefix.'.*.field' => [
                    Rule::in($resource->getNestedFields($this)),
                    "required_without:$prefix.*.nested",
                    'string'
                ],
                $prefix.'.*.operator' => [
                    Rule::in('=', '!=', '>', '>=', '<', '<=', 'like', 'not like', 'in', 'not in'),
                    'string',
                ],
                $prefix.'.*.value' => [
                    "required_without:$prefix.*.nested"
                ],
                $prefix.'.*.type' => [
                    'sometimes',
                    Rule::in('or', 'and')
                ],
                $prefix.'.*.nested' => !$isMaxDepth ? [
                    'sometimes',
                    "prohibits:$prefix.*.field,$prefix.*.operator,$prefix.*.value",
                    'prohibits:value',
                    'array'
                ] : [
                    'prohibited'
                ]
            ],
            !$isMaxDepth ? $this->filtersRules($resource, $prefix.'.*.nested', true) : []
        );

        return $rules;
    }

    /**
     * Define the validation rules for scopes within the search request.
     *
     * @param Resource $resource
     * @param string $prefix
     * @return array
     */
    protected function scopesRules(Resource $resource, string $prefix) {
        $rules = [
            $prefix.'.*.name' => [
                Rule::in($resource->scopes($this)),
                'required',
                'string'
            ],
            $prefix.'.*.parameters' => [
                'sometimes',
                'array'
            ]
        ];

        return $rules;
    }

    /**
     * Define the validation rules for instructions within the search request.
     *
     * @param Resource $resource
     * @param string $prefix
     * @return array
     */
    protected function instructionsRules(Resource $resource, string $prefix) {
        $rules = [
            $prefix.'.*.name' => [
                Rule::in(
                    collect($resource->instructions($this))
                        ->map(function ($instruction) { return $instruction->uriKey(); })
                        ->toArray()
                ),
                'required',
                'string'
            ],
            $prefix.'.*.fields' => [
                'sometimes',
                'array'
            ],
            $prefix.'.*' => [
                Instruction::make()
                    ->resource($resource)
            ]
        ];

        return $rules;
    }

    /**
     * Define the validation rules for sorting options within the search request.
     *
     * @param Resource $resource
     * @param string $prefix
     * @return array
     */
    protected function sortsRules(Resource $resource, string $prefix) {
        $rules = [
            $prefix.'.*.field' => [
                Rule::in($resource->fields($this)),
                'required',
                'string'
            ],
            $prefix.'.*.direction' => [
                'sometimes',
                Rule::in('desc', 'asc')
            ]
        ];

        return $rules;
    }

    /**
     * Define the validation rules for selecting fields within the search request.
     *
     * @param Resource $resource
     * @param string $prefix
     * @return array
     */
    protected function selectsRules(Resource $resource, string $prefix) {
        $rules = [
            $prefix.'.*.field' => [
                Rule::in($resource->fields($this)),
                'required',
                'string'
            ]
        ];

        return $rules;
    }

    /**
     * Define the validation rules for including related resources within the search request.
     *
     * @param Resource $resource
     * @return array
     */
    protected function includesRules(Resource $resource) {
        return [
            'includes.*.relation' => [
                'required',
                Rule::in(
                    array_keys(
                        $resource->nestedRelations(app()->make(RestRequest::class))
                    )
                )
            ],
            'includes.*.includes' => [
                'prohibited'
            ],
            'includes.*' => [
                Includable::make()
                    ->resource($resource)
            ]
        ];
    }

    /**
     * Define the validation rules for aggregate functions within the search request.
     *
     * @param Resource $resource
     * @param string $prefix
     * @return array
     */
    protected function aggregatesRules(Resource $resource, string $prefix) {
        return [
            $prefix.'.*.relation' => [
                'required',
                Rule::in(
                    array_keys(
                        $resource->nestedRelations(app()->make(RestRequest::class))
                    )
                )
            ],
            $prefix.'.*.type' => [
                Rule::in(['count', 'min', 'max', 'avg', 'sum', 'exists'])
            ],
            $prefix.'.*.field' => [
                'required_if:'.$prefix.'.*.type,min,max,avg,sum',
                'prohibited_if:'.$prefix.'.*.type,count,exists'
            ],
            $prefix.'.*' => [
                AggregateField::make()
                    ->resource($resource)
            ],
            $prefix.'.*.filters' => [
                AggregateFilterable::make()
                    ->resource($resource)
            ]
        ];
    }
}

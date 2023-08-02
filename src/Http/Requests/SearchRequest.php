<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\Includable;

class SearchRequest extends RestRequest
{

    public function rules()
    {
        return $this->searchRules($this->route()->controller::newResource());
    }
    public function searchRules(Resource $resource, $prefix = '')
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
            [
                'limit' => ['sometimes', 'integer', Rule::in($resource->exposedLimits($this))],
                'page' => ['sometimes', 'integer']
            ],
            $prefix === '' ? ['includes' => ['sometimes', 'array']] : [],
            $prefix === '' ? $this->includesRules($resource) : [],
        );
    }

    // @TODO: For now it's prohibited to have more than one nested depth, is this needed ?
    protected function filtersRules(Resource $resource, string $prefix, $isMaxDepth = false) {
        $rules = array_merge(
            [
                $prefix.'.*.field' => [
                    Rule::in($resource->getNestedExposedFields($this)),
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

    protected function scopesRules(Resource $resource, string $prefix) {
        $rules = [
            $prefix.'.*.name' => [
                Rule::in($resource->exposedScopes($this)),
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

    protected function sortsRules(Resource $resource, string $prefix) {
        $rules = [
            $prefix.'.*.field' => [
                Rule::in($resource->exposedFields($this)),
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

    protected function selectsRules(Resource $resource, string $prefix) {
        $rules = [
            $prefix.'.*.field' => [
                Rule::in($resource->exposedFields($this)),
                'required',
                'string'
            ]
        ];

        return $rules;
    }

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
}

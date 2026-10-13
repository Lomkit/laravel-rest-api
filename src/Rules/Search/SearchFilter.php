<?php

namespace Lomkit\Rest\Rules\Search;

use Closure;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Rules\Resource\ResourceFieldOrNested;
use Lomkit\Rest\Rules\Resource\ResourceRelationOrNested;
use Lomkit\Rest\Rules\RestRule;

class SearchFilter extends RestRule
{
    /**
     * How many levels of nesting this filter already sits under.
     */
    protected int $depth = 0;

    /**
     * The relation the resource is reached through, when filtering inside a relation filter.
     */
    protected ?Relation $relation = null;

    /**
     * Set the nesting level this filter sits at.
     */
    public function setDepth(int $depth): static
    {
        $this->depth = $depth;

        return $this;
    }

    /**
     * Set the relation the resource is reached through, giving its pivot fields.
     */
    public function setRelation(?Relation $relation): static
    {
        $this->relation = $relation;

        return $this;
    }

    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);
        $isScoutMode = $request->isScoutMode();

        $fieldsValidation = $isScoutMode ?
            Rule::in($this->resource->getScoutFields($request)) :
            (new ResourceFieldOrNested())->setResource($this->resource)->setRelation($this->relation);

        $allowedOperators = $isScoutMode ?
            ['=', 'in', 'not in'] :
            ['=', '!=', '>', '>=', '<', '<=', 'like', 'not like', 'ilike', 'not ilike', 'in', 'not in', 'between', 'not between', 'is null', 'is not null'];

        $nestingAllowed = !$isScoutMode && $this->depth < config('rest.search.max_nesting_depth', 1);

        $isRelationFilter = is_array($value) && isset($value['relation']);
        $relation = $isRelationFilter && is_string($value['relation']) ? $this->resource->relation($value['relation']) : null;

        // The nested filters of a relation filter apply to the related resource
        $nestedRule = $isRelationFilter ?
            ($relation === null ? [] : [
                (new SearchFilter())
                    ->setResource($relation->resource())
                    ->setRelation($relation)
                    ->setDepth($this->depth + 1),
            ]) : [
                (new SearchFilter())
                    ->setResource($this->resource)
                    ->setRelation($this->relation)
                    ->setDepth($this->depth + 1),
            ];

        $rules = [
            $attribute.'.field' => [
                'string',
                'required_without_all:'.$attribute.'.nested,'.$attribute.'.relation',
                $fieldsValidation,
            ],
            $attribute.'.relation' => !$isScoutMode ? [
                'bail',
                'sometimes',
                'string',
                // A pivot is not a relation the query could check the existence of
                'not_regex:/(^|\.)pivot(\.|$)/',
                'prohibits:'.$attribute.'.field,'.$attribute.'.operator,'.$attribute.'.value',
                (new ResourceRelationOrNested())->setResource($this->resource),
            ] : [
                'prohibited',
            ],
            $attribute.'.exists' => !$isScoutMode && $isRelationFilter ? [
                'sometimes',
                'boolean',
            ] : [
                'prohibited',
            ],
            $attribute.'.nested' => $nestingAllowed ? [
                'sometimes',
                'prohibits:'.$attribute.'.field,'.$attribute.'.operator,'.$attribute.'.value',
                'array',
            ] : [
                'prohibited',
            ],
            $attribute.'.nested.*' => $nestedRule,
            $attribute.'.operator' => [
                'string',
                Rule::in($allowedOperators),
            ],
            $attribute.'.type' => !$isScoutMode ? [
                'sometimes',
                Rule::in(['or', 'and']),
            ] : [
                'prohibited',
            ],
            $attribute.'.value' => [
                'exclude_if:'.$attribute.'.value,null',
                'required_without_all:'.$attribute.'.nested,'.$attribute.'.relation',
            ],
        ];

        if ($isScoutMode || !is_array($value)) {
            return $rules;
        }

        // Some operators expect a value of their own shape
        $rules[$attribute.'.value'] = match ($value['operator'] ?? null) {
            'ilike', 'not ilike' => ['required', 'string'],
            'between', 'not between' => ['required', 'array', 'size:2', function (string $attribute, mixed $value, Closure $fail) {
                // A bound is a single value, the query builder would flatten anything else
                if (is_array($value) && array_filter($value, 'is_array') !== []) {
                    $fail('The \'value\' field is not valid.');
                }
            }],
            'is null', 'is not null' => ['prohibited'],
            default => $rules[$attribute.'.value'],
        };

        return $rules;
    }
}

<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\Resource\ResourceFieldOrNested;
use Lomkit\Rest\Rules\RestRule;

class SearchFilter extends RestRule
{
    /**
     * How many levels of nesting this filter already sits under.
     */
    protected int $depth = 0;

    /**
     * Set the nesting level this filter sits at.
     */
    public function setDepth(int $depth): static
    {
        $this->depth = $depth;

        return $this;
    }

    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);
        $isScoutMode = $request->isScoutMode();

        $fieldsValidation = $isScoutMode ?
            Rule::in($this->resource->getScoutFields($request)) :
            (new ResourceFieldOrNested())->setResource($this->resource);

        $allowedOperators = $isScoutMode ?
            ['=', 'in', 'not in'] :
            ['=', '!=', '>', '>=', '<', '<=', 'like', 'not like', 'in', 'not in'];

        $nestingAllowed = !$isScoutMode && $this->depth < config('rest.search.max_nesting_depth', 1);

        return [
            $attribute.'.field' => [
                'string',
                'required_without:'.$attribute.'.nested',
                $fieldsValidation,
            ],
            $attribute.'.nested' => $nestingAllowed ? [
                'sometimes',
                'prohibits:'.$attribute.'.field,'.$attribute.'.operator,'.$attribute.'.value',
                'array',
            ] : [
                'prohibited',
            ],
            $attribute.'.nested.*' => [
                (new SearchFilter())
                    ->setResource($this->resource)
                    ->setDepth($this->depth + 1),
            ],
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
                'required_without:'.$attribute.'.nested',
            ],
        ];
    }
}

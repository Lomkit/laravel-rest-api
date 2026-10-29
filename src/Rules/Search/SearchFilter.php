<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\Concerns\DependsOnTextSearch;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\Resource\ResourceFieldOrNested;
use Lomkit\Rest\Rules\RestRule;

class SearchFilter extends RestRule
{
    use DependsOnTextSearch;

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

    /** @return array<string, mixed> */
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;

        $fieldsValidation = $this->textSearch ?
            new OneOf($this->resource->getScoutFields($request)) :
            (new ResourceFieldOrNested)->setResource($this->resource);

        $allowedOperators = $this->textSearch ?
            ['=', 'in', 'not in'] :
            ['=', '!=', '>', '>=', '<', '<=', 'like', 'not like', 'in', 'not in'];

        $nestingAllowed = ! $this->textSearch && $this->depth < config('rest.search.max_nesting_depth', 1);

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
                (new SearchFilter)
                    ->setResource($this->resource)
                    ->setDepth($this->depth + 1)
                    ->forTextSearch($this->textSearch),
            ],
            $attribute.'.operator' => [
                'string',
                Rule::in($allowedOperators),
            ],
            $attribute.'.type' => ! $this->textSearch ? [
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

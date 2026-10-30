<?php

namespace Lomkit\Rest\Rules\Search;

use Lomkit\Rest\Rules\Concerns\ReachesRelations;
use Lomkit\Rest\Rules\Resource\ResourceRelationOrNested;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Validation\RuleCompiler;

class SearchInclude extends RestRule
{
    use ReachesRelations;

    #[\Override]
    public function compile(RuleCompiler $into, string $attribute, mixed $value): void
    {
        $this->reachRelation($into, is_array($value) ? ($value['relation'] ?? null) : null, $attribute);

        parent::compile($into, $attribute, $value);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $relation = is_array($value) ? ($value['relation'] ?? null) : null;
        $relationResource = is_string($relation) ? $this->resource->relation($relation)?->resource() : null;

        return array_merge(
            is_null($relationResource) ? [] : (new Search)->setResource($relationResource)->buildValidationRules($attribute, $value),
            [
                $attribute.'.relation' => [
                    'required',
                    (new ResourceRelationOrNested)->setResource($this->resource),
                ],
                $attribute.'.text' => [
                    'prohibited',
                ],
                $attribute.'.alias' => [
                    'sometimes',
                    'string',
                    'max:255',
                    // The alias is used verbatim as a model relation key and as a response key,
                    // so it is restricted to what a relation name could have been.
                    'regex:/^[A-Za-z_][A-Za-z0-9_]*$/',
                    new SearchIncludeAlias($this->resource, is_string($relation) ? $relation : ''),
                ],
            ]
        );
    }
}

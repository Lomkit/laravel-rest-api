<?php

namespace Lomkit\Rest\Rules\Search;

use Lomkit\Rest\Rules\Resource\ResourceRelationOrNested;
use Lomkit\Rest\Rules\RestRule;

class SearchInclude extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;

        $relationResource = $this->resource->relation($value['relation'])?->resource();

        if ($request->isScoutMode()) {
            return [];
        }

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
                    (new SearchIncludeAlias)
                        ->setResource($this->resource)
                        ->setRelation($value['relation'] ?? ''),
                ],
            ]
        );
    }
}

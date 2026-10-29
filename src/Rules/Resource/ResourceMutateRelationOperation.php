<?php

namespace Lomkit\Rest\Rules\Resource;

use Lomkit\Rest\Rules\RestRule;

class ResourceMutateRelationOperation extends RestRule
{
    /** @return array<string, string> */
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;
        $operation = is_array($value) ? ($value['operation'] ?? null) : null;
        $rules = [];

        foreach ($this->resource->getRelations($request) as $relation) {
            if ($operation === 'update') {
                if ($relation->isProhibitedOnUpdate($request)) {
                    $rules[$attribute.'.relations.'.$relation->relation] = 'prohibited';
                }

                if ($relation->isRequiredOnUpdate($request)) {
                    $rules[$attribute.'.relations.'.$relation->relation] = 'required';
                }
            }

            if ($operation === 'create') {
                if ($relation->isProhibitedOnCreation($request)) {
                    $rules[$attribute.'.relations.'.$relation->relation] = 'prohibited';
                }

                if ($relation->isRequiredOnCreation($request)) {
                    $rules[$attribute.'.relations.'.$relation->relation] = 'required';
                }
            }
        }

        return $rules;
    }
}

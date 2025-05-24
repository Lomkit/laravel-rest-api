<?php

namespace Lomkit\Rest\Rules\Resource;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Rules\RestRule;
use Closure;

class ResourceMutateRelationOperation extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);
        $resourceRelations = $this->resource->relations($request);

        $rules = [];

        /**
         * @var Relation $relation
         */
        foreach ($resourceRelations as $relation) {
            if ($value['operation'] === 'update') {
                if ($relation->isProhibitedOnUpdate($request)) {
                    $rules[$attribute.'.relations.'.$relation->relation] = 'prohibited';
                }

                if ($relation->isRequiredOnUpdate($request)) {
                    $rules[$attribute.'.relations.'.$relation->relation] = 'required';
                }
            }

            if ($value['operation'] === 'create') {
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
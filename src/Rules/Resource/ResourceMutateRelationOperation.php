<?php

namespace Lomkit\Rest\Rules\Resource;

use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Rules\RestRule;

class ResourceMutateRelationOperation extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;
        $resourceRelations = $this->resource->getRelations($request);

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

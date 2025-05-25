<?php

namespace Lomkit\Rest\Rules\Search;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\Resource\ResourceRelationOrNested;
use Lomkit\Rest\Rules\RestRule;

class SearchInclude extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);

        $relationResource = $this->resource->relation($value['relation'])?->resource();

        if ($request->isScoutMode()) {
            return [];
        }

        return array_merge(
            is_null($relationResource) ? [] : (new Search())->setResource($relationResource)->buildValidationRules($attribute, $value),
            [
                $attribute.'.relation' => [
                    'required',
                    (new ResourceRelationOrNested())->setResource($this->resource),
                ],
                $attribute.'.includes' => [
                    'prohibited',
                ],
                $attribute.'.text' => [
                    'prohibited',
                ],
            ]
        );
    }
}

<?php

namespace Lomkit\Rest\Rules\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\RestRule;

class ResourceCustomRules extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);

        if ($value['operation'] === 'create') {
            $rules = $this->resource->createRules($request);
        } elseif ($value['operation'] === 'update') {
            $rules = $this->resource->updateRules($request);
        } else {
            // No rules needed
            return [];
        }

        $rules = array_merge_recursive(
            $rules,
            $this->resource->rules($request),
        );

        return collect($rules)
            ->mapWithKeys(function ($item, $key) use ($attribute) {
                return [$attribute.'.attributes.'.$key => $item];
            })->toArray();
    }
}
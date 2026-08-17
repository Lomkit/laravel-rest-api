<?php

namespace Lomkit\Rest\Rules\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\RestRule;

class ResourceCustomRules extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);

        $operation = is_array($value) ? ($value['operation'] ?? null) : null;

        $rules = match ($operation) {
            'create'                   => $this->resource->createRules($request),
            'update', 'sync', 'toggle' => $this->resource->updateRules($request),
            default                    => null,
        };

        if (is_null($rules)) {
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

<?php

namespace Lomkit\Rest\Rules\Resource;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Fields\WriteRules;
use Lomkit\Rest\Rules\RestRule;

class ResourceCustomRules extends RestRule
{
    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $operation = MutationOperation::tryFromEntry($value)?->writeOperation();

        if ($operation === null || ! is_array($value) || (array_key_exists('attributes', $value) && ! is_array($value['attributes']))) {
            return [];
        }

        $request = $this->resource->context()->request;

        return WriteRules::of(
            $this->resource->getFields($request),
            $operation,
            $operation === WriteOperation::Create ? $this->resource->createRules($request) : $this->resource->updateRules($request),
            $this->resource->rules($request),
        )->under($attribute.'.attributes.');
    }
}

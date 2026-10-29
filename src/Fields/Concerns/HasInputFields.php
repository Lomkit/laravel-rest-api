<?php

namespace Lomkit\Rest\Fields\Concerns;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Requests\RestRequest;

trait HasInputFields
{
    /** @return list<Field> */
    public function fields(RestRequest $request): array
    {
        return [];
    }

    public function getFields(RestRequest $request): FieldSet
    {
        return FieldSet::forInputs($this->fields($request), static::class.'::fields()');
    }
}

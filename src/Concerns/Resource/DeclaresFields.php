<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Requests\RestRequest;

trait DeclaresFields
{
    /** @return list<Field> */
    public function fields(RestRequest $request): array
    {
        return [];
    }

    /** @return list<string> */
    public function alwaysSelect(RestRequest $request): array
    {
        return [];
    }

    public function getFields(): FieldSet
    {
        return $this->context()->definition($this)->fields();
    }
}

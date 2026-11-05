<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;

trait DeclaresFields
{
    /** @return list<Field> */
    public function fields(): array
    {
        return [];
    }

    /** @return list<string> */
    public function alwaysSelect(): array
    {
        return [];
    }

    public function getFields(): FieldSet
    {
        return $this->context()->definition($this)->fields();
    }
}

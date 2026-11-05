<?php

namespace Lomkit\Rest\Fields\Concerns;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;

trait HasInputFields
{
    /** @return list<Field> */
    public function fields(): array
    {
        return [];
    }

    public function getFields(): FieldSet
    {
        return FieldSet::forInputs($this->fields(), static::class.'::fields()');
    }
}

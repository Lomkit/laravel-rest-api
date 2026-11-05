<?php

namespace Lomkit\Rest\Fields\Concerns;

use Lomkit\Rest\Fields\Field;

trait HasInputFields
{
    /** @return list<Field> */
    public function fields(): array
    {
        return [];
    }
}

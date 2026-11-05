<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Fields\Field;

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
}

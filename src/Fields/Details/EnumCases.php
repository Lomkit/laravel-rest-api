<?php

namespace Lomkit\Rest\Fields\Details;

use Lomkit\Rest\Fields\Enum;

final readonly class EnumCases
{
    public function __construct(private Enum $field) {}

    /** @return array{values: list<int|string>, offered?: list<int|string>} */
    public function toArray(): array
    {
        if ($this->field->offersEveryCase()) {
            return ['values' => $this->field->values()];
        }

        return ['values' => $this->field->values(), 'offered' => $this->field->offeredValues()];
    }
}

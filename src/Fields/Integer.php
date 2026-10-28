<?php

namespace Lomkit\Rest\Fields;

class Integer extends Number
{
    public function baseRules(): array
    {
        return ['integer'];
    }

    public function type(): string
    {
        return 'integer';
    }

    protected function castValue(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $value;
    }
}

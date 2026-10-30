<?php

namespace Lomkit\Rest\Fields;

class Integer extends Number
{
    #[\Override]
    public function baseRules(): array
    {
        return ['integer'];
    }

    #[\Override]
    public function type(): string
    {
        return 'integer';
    }

    #[\Override]
    protected function castValue(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $value;
    }
}

<?php

namespace Lomkit\Rest\Fields;

class Boolean extends Field
{
    public function operators(): array
    {
        return ['=', '!='];
    }

    public function baseRules(): array
    {
        return ['boolean'];
    }

    public function type(): string
    {
        return 'boolean';
    }

    protected function castValue(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }
}

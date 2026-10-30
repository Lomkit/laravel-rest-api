<?php

namespace Lomkit\Rest\Fields;

class Boolean extends Field
{
    #[\Override]
    public function operators(): array
    {
        return ['=', '!='];
    }

    #[\Override]
    public function baseRules(): array
    {
        return ['boolean'];
    }

    #[\Override]
    public function type(): string
    {
        return 'boolean';
    }

    #[\Override]
    protected function castValue(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }
}

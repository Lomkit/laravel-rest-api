<?php

namespace Lomkit\Rest\Fields;

use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\Type;

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
    public function toSchema(): Type
    {
        return new BooleanType;
    }

    #[\Override]
    protected function castValue(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }
}

<?php

namespace Lomkit\Rest\Fields;

use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\Type;

class Number extends Field
{
    #[\Override]
    public function operators(): array
    {
        return ['=', '!=', '>', '>=', '<', '<=', 'between', 'not between', 'in', 'not in'];
    }

    #[\Override]
    public function baseRules(): array
    {
        return ['numeric'];
    }

    #[\Override]
    public function type(): string
    {
        return 'number';
    }

    #[\Override]
    public function toSchema(): Type
    {
        return new NumberType;
    }

    #[\Override]
    public function render(mixed $value): mixed
    {
        if (! is_numeric($value)) {
            return $value;
        }

        return $value + 0;
    }

    #[\Override]
    protected function castValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (! is_numeric($value)) {
            return $value;
        }

        return $value + 0;
    }
}

<?php

namespace Lomkit\Rest\Fields;

use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Database\Eloquent\Model;

class Boolean extends Field
{
    private bool $converted = false;

    #[\Override]
    public function bind(Model $model): void
    {
        $this->converted = $this->convertedBy($model);
    }

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
    public function render(mixed $value): mixed
    {
        if ($this->converted || $value === null) {
            return $value;
        }

        return $this->castValue($value);
    }

    #[\Override]
    protected function castValue(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }
}

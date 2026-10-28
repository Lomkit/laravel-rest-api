<?php

namespace Lomkit\Rest\Fields;

use Stringable;

class Text extends Field
{
    public function operators(): array
    {
        return ['=', '!=', 'like', 'not like', 'ilike', 'not ilike', 'in', 'not in'];
    }

    public function baseRules(): array
    {
        return ['string'];
    }

    public function type(): string
    {
        return 'text';
    }

    protected function castValue(mixed $value): mixed
    {
        return is_scalar($value) || $value instanceof Stringable ? (string) $value : $value;
    }
}

<?php

namespace Lomkit\Rest\Fields;

use Stringable;

class Text extends Field
{
    #[\Override]
    public function operators(): array
    {
        return ['=', '!=', 'like', 'not like', 'ilike', 'not ilike', 'in', 'not in'];
    }

    #[\Override]
    public function baseRules(): array
    {
        return ['string'];
    }

    #[\Override]
    public function type(): string
    {
        return 'text';
    }

    #[\Override]
    protected function supportsTextSearch(): bool
    {
        return true;
    }

    #[\Override]
    protected function castValue(mixed $value): mixed
    {
        return is_scalar($value) || $value instanceof Stringable ? (string) $value : $value;
    }
}

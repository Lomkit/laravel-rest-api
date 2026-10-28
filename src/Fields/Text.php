<?php

namespace Lomkit\Rest\Fields;

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
}

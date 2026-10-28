<?php

namespace Lomkit\Rest\Fields;

class Number extends Field
{
    public function operators(): array
    {
        return ['=', '!=', '>', '>=', '<', '<=', 'between', 'not between', 'in', 'not in'];
    }

    public function baseRules(): array
    {
        return ['numeric'];
    }

    public function type(): string
    {
        return 'number';
    }
}

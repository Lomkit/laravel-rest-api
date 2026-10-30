<?php

namespace Lomkit\Rest\Fields;

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
}

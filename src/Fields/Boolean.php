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
}

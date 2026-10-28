<?php

namespace Lomkit\Rest\Fields;

class Integer extends Number
{
    public function baseRules(): array
    {
        return ['integer'];
    }

    public function type(): string
    {
        return 'integer';
    }
}

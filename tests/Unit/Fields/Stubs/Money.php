<?php

namespace Lomkit\Rest\Tests\Unit\Fields\Stubs;

use Lomkit\Rest\Fields\Field;

class Money extends Field
{
    public function operators(): array
    {
        return ['=', '>', '>=', '<', '<=', 'between'];
    }

    public function baseRules(): array
    {
        return ['numeric', 'min:0'];
    }
}

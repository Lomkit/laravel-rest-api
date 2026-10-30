<?php

namespace Lomkit\Rest\Tests\Unit\Fields\Stubs;

use Lomkit\Rest\Fields\Field;

class Money extends Field
{
    #[\Override]
    public function operators(): array
    {
        return ['=', '>', '>=', '<', '<=', 'between'];
    }

    #[\Override]
    public function baseRules(): array
    {
        return ['numeric', 'min:0'];
    }
}

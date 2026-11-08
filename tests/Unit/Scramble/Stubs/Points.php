<?php

namespace Lomkit\Rest\Tests\Unit\Scramble\Stubs;

use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\Field;

class Points extends Field
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

    #[\Override]
    public function toSchema(): Type
    {
        return new NumberType;
    }
}

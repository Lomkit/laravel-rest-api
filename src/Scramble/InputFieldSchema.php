<?php

namespace Lomkit\Rest\Scramble;

use BackedEnum;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\Details\RuleDescriptions;
use Lomkit\Rest\Fields\Field;

final class InputFieldSchema
{
    public static function of(Field $field): Type
    {
        $type = FieldSchema::written($field);
        $rules = (new RuleDescriptions($field->getRules()->shared()))->toArray();

        if ($rules !== []) {
            $type->setDescription('Rules: '.implode(', ', $rules));
        }

        if (! $field->hasDefault()) {
            return $type;
        }

        $default = $field->defaultValue();
        $default = $default instanceof BackedEnum ? $default->value : $default;

        if (is_scalar($default) || is_array($default) || $default === null) {
            $type->default($default);
        }

        return $type;
    }
}

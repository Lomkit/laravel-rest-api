<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\Field;

final class FieldSchema
{
    public static function of(Field $field): Type
    {
        $type = $field->toSchema();

        if ($field->isNullable()) {
            $type->nullable(true);
        }

        if ($field->getExamples() !== []) {
            $type->examples($field->getExamples());
        }

        if ($field->getMeta() !== []) {
            $type->setExtensionProperty('lomkit-meta', $field->getMeta());
        }

        return $type;
    }
}

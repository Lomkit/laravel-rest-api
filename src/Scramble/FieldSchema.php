<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\Field;

final class FieldSchema
{
    public static function of(Field $field): Type
    {
        return self::described($field, self::value($field));
    }

    public static function written(Field $field): Type
    {
        return self::described($field, self::withExamples($field, $field->toWriteSchema()));
    }

    public static function value(Field $field): Type
    {
        return self::withExamples($field, $field->toSchema());
    }

    private static function described(Field $field, Type $type): Type
    {
        if ($field->isNullable()) {
            $type->nullable(true);
        }

        if ($field->getMeta() !== []) {
            $type->setExtensionProperty('lomkit-meta', $field->getMeta());
        }

        return $type;
    }

    private static function withExamples(Field $field, Type $type): Type
    {
        if ($field->getExamples() !== []) {
            $type->examples($field->getExamples());
        }

        return $type;
    }
}

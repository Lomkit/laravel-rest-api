<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;

final class FieldExample
{
    public static function of(Field $field): mixed
    {
        $examples = $field->getExamples();

        if ($examples !== []) {
            return $examples[0];
        }

        return $field instanceof ID ? self::key($field) : self::typed($field->toSchema());
    }

    public static function key(ID $id): int|string
    {
        return match ($id->resolvedKeyType()) {
            'int' => 1,
            'string' => 'a',
            'uuid' => '00000000-0000-0000-0000-000000000000',
            'ulid' => '00000000000000000000000000',
        };
    }

    private static function typed(Type $type): mixed
    {
        if ($type->enum !== []) {
            return $type->enum[0];
        }

        return match ($type->type) {
            'integer', 'number' => 1,
            'boolean' => true,
            default => match ($type->format) {
                'date' => '2026-01-01',
                'date-time' => '2026-01-01T00:00:00Z',
                default => 'a',
            },
        };
    }
}

<?php

namespace Lomkit\Rest\Scramble;

use Closure;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\WriteOperation;
use stdClass;

final class FieldExample
{
    public static function of(Field $field, ?WriteOperation $operation = null): mixed
    {
        return self::from($field, $field->toSchema(...), $operation);
    }

    public static function written(Field $field, ?WriteOperation $operation = null): mixed
    {
        return self::from($field, $field->toWriteSchema(...), $operation);
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

    /** @param Closure(): Type $schema */
    private static function from(Field $field, Closure $schema, ?WriteOperation $operation): mixed
    {
        $examples = $field->getExamples();

        if ($examples !== []) {
            return $examples[0];
        }

        if ($field instanceof ID) {
            return self::key($field);
        }

        $rules = $field->getRules();

        return RuleExample::honour($operation === null ? $rules->shared() : $rules->forWrite($operation), self::typed($schema()));
    }

    private static function typed(Type $type): mixed
    {
        if ($type->enum !== []) {
            return $type->enum[0];
        }

        return match ($type->type) {
            'integer', 'number' => 1,
            'boolean' => true,
            'object' => new stdClass,
            default => match ($type->format) {
                'date' => '2026-01-01',
                'date-time' => '2026-01-01T00:00:00Z',
                default => 'a',
            },
        };
    }
}

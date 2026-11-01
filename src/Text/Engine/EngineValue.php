<?php

namespace Lomkit\Rest\Text\Engine;

use BackedEnum;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;

final class EngineValue
{
    private const float LARGEST_EXACT_INTEGER = 9007199254740991.0;

    public static function of(Field $field, mixed $value): int|float|string|bool|null
    {
        return match (true) {
            $field instanceof ID => $field->resolvedKeyType() === 'int' ? self::integer($value) : self::string($value),
            $field instanceof Integer => self::integer($value),
            $field instanceof Number => self::number($value),
            $field instanceof Boolean => self::boolean($value),
            $field instanceof Enum => self::enum($field, $value),
            $field instanceof Text => self::string($value),
            default => null,
        };
    }

    private static function integer(mixed $value): ?int
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }

    private static function number(mixed $value): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = $value + 0;

        if (is_int($number)) {
            return $number;
        }

        if (! is_finite($number)) {
            return null;
        }

        return floor($number) === $number && abs($number) <= self::LARGEST_EXACT_INTEGER ? (int) $number : $number;
    }

    private static function boolean(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value) => $value,
            $value === 1, $value === '1' => true,
            $value === 0, $value === '0' => false,
            default => null,
        };
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) || is_int($value) ? (string) $value : null;
    }

    private static function enum(Enum $field, mixed $value): int|string|null
    {
        $class = $field->enumClass();

        if ($value instanceof BackedEnum) {
            return $value instanceof $class ? $value->value : null;
        }

        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        foreach ($field->values() as $case) {
            if ((string) $case === (string) $value) {
                return $case;
            }
        }

        return null;
    }
}

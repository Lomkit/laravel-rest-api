<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidFieldException extends LogicException
{
    public static function emptyName(): self
    {
        return new self('A field name cannot be empty.');
    }

    public static function dottedName(string $name): self
    {
        return new self("The field name [{$name}] contains a dot. Dots are reserved for relation paths.");
    }

    public static function notAField(string $owner, int $index, mixed $entry): self
    {
        if (is_string($entry)) {
            $example = $entry === 'id' ? "ID::make('id')" : "Text::make('{$entry}')";

            return new self("{$owner} returned a string at index {$index} ('{$entry}'). Return Field objects, for example {$example}.");
        }

        return new self("{$owner} returned ".get_debug_type($entry)." at index {$index}. Return Field objects.");
    }

    public static function duplicateName(string $owner, string $name): self
    {
        return new self("{$owner} declares the field [{$name}] twice.");
    }

    public static function writeRulesOnInput(string $owner, string $name): self
    {
        return new self("{$owner} declares creation or update rules on the input field [{$name}]. Input fields take rules() only.");
    }

    public static function numericInputName(string $owner, string $name): self
    {
        return new self("{$owner} declares the input field [{$name}] with a numeric name. Input names must not be numeric.");
    }

    public static function unknownField(string $name): self
    {
        return new self("Unknown field [{$name}].");
    }

    public static function unknownOperator(string $operator): self
    {
        return new self("Unknown filter operator [{$operator}].");
    }

    public static function unsupportedOperator(string $field, string $operator): self
    {
        return new self("The field [{$field}] does not support the operator [{$operator}].");
    }

    public static function notSortable(string $field): self
    {
        return new self("The field [{$field}] is not sortable.");
    }

    public static function unknownSortDirection(string $direction): self
    {
        return new self("Unknown sort direction [{$direction}]. Use asc or desc.");
    }

    public static function unknownKeyType(string $field, string $keyType): self
    {
        return new self("The ID field [{$field}] has an unknown key type [{$keyType}]. Use int, string, uuid or ulid.");
    }

    public static function missingEnumClass(string $field): self
    {
        return new self("The enum field [{$field}] has no enum class. Pass it to Enum::make() or cast the attribute to an enum on the model.");
    }
}

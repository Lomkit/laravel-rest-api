<?php

namespace Lomkit\Rest\Exceptions;

use Lomkit\Rest\Fields\FieldReduction;

final class InvalidFieldExtensionException extends ExtensionException
{
    public static function unknownField(string $name, string $class, string $resource, FieldReduction $reduction, string $field): self
    {
        return self::about($class, 'The '.self::extension($name, $class)." calls {$reduction->value}('{$field}') on {$resource}, whose fields() declares no field [{$field}]. Name a field that fields() returns; FieldsContext::declares() tells whether it does.");
    }

    public static function hidesVersion(string $name, string $class, string $resource, string $field): self
    {
        return self::about($class, 'The '.self::extension($name, $class)." hides [{$field}], the row version of {$resource}: versioned() reads it on each update and returns it in a 409. Call readOnly(), unfilterable() or unsortable() on it instead, or turn versioned() off.");
    }

    public static function hidesKey(string $name, string $class, string $resource, string $field): self
    {
        return self::about($class, 'The '.self::extension($name, $class)." hides [{$field}], the key of the model of {$resource}: writes, actions and their responses name rows by it. Call readOnly(), unfilterable() or unsortable() on it instead.");
    }

    public static function hidesSearchable(string $name, string $class, string $resource, string $field): self
    {
        return self::about($class, 'The '.self::extension($name, $class)." hides [{$field}], a searchable() field of {$resource}: its text search would still match the value. Remove searchable() from the field or keep it visible.");
    }

    public static function hidesPerimeterAttribute(string $name, string $class, string $resource, string $field): self
    {
        return self::about($class, 'The '.self::extension($name, $class)." hides [{$field}], an attribute that ScoutEngine::perimeter() of {$resource} reads for every user. Keep it visible: clients cannot filter or sort on a perimeter attribute anyway.");
    }

    public static function readsNarrowedFields(string $name, string $class, string $resource): self
    {
        return self::about($class, 'The '.self::extension($name, $class)." reads the fields or the text search of {$resource} while the NarrowsFields extensions narrow them. Read the declared names with FieldsContext::declares() instead.");
    }

    private static function extension(string $name, string $class): string
    {
        return "[{$name}] extension {$class}";
    }
}

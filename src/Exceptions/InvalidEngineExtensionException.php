<?php

namespace Lomkit\Rest\Exceptions;

use Lomkit\Rest\Text\Engine\ResourceIndexList;

final class InvalidEngineExtensionException extends InvalidExtensionException
{
    public static function notAnIndexAttribute(string $name, string $class, string $resource, int $index, string $type): self
    {
        return self::about($name, "The [{$name}] extension {$class} declares for {$resource} an index attribute of type [{$type}] at position {$index}. Return fields, such as Integer::make('tenant_id'), or StringListAttribute::make('readers') for a list of strings.");
    }

    public static function invalidIndexAttributeName(string $name, string $class, string $resource, string $attribute): self
    {
        return self::about($name, "The [{$name}] extension {$class} declares for {$resource} the index attribute [{$attribute}]. Attribute names start with a letter or an underscore and hold only letters, digits and underscores, and [__soft_deleted] is reserved.");
    }

    public static function unsupportedIndexAttributeType(string $name, string $class, string $resource, string $attribute, string $type): self
    {
        return self::about($name, "The [{$name}] extension {$class} declares for {$resource} the index attribute [{$attribute}] as [{$type}]. Index attributes are ID, Integer, Number, Boolean, Text or Enum fields, or a StringListAttribute; declare a timestamp as an Integer.");
    }

    public static function callbackIndexAttribute(string $name, string $class, string $resource, string $attribute): self
    {
        return self::about($name, "The [{$name}] extension {$class} declares for {$resource} the index attribute [{$attribute}], which filters or sorts through a callback. Declare index attributes without filterUsing() or sortUsing().");
    }

    public static function unfilterableIndexAttribute(string $name, string $class, string $resource, string $attribute): self
    {
        return self::about($name, "The [{$name}] extension {$class} declares for {$resource} the index attribute [{$attribute}], which accepts no filter operator on this engine. Declare an attribute that the engine compares, without filterable(false).");
    }

    public static function repeatedIndexAttribute(string $name, string $class, string $resource, string $attribute): self
    {
        return self::about($name, "The [{$name}] extension {$class} declares for {$resource} the index attribute [{$attribute}] more than once. Declare it once.");
    }

    public static function indexAttributeTakenByResource(string $name, string $class, string $resource, string $attribute, ResourceIndexList $list): self
    {
        return $list === ResourceIndexList::Filterable
            ? self::about($name, "The [{$name}] extension {$class} declares for {$resource} the index attribute [{$attribute}], which the filterable() list of its ScoutEngine already declares. Index attributes keep their real name: index the attribute under another name for one of them, or leave {$resource} out of the extension with appliesTo().")
            : self::taken($name, $class, $resource, $attribute, "the {$list->value}() list of its ScoutEngine");
    }

    public static function indexAttributeTakenByExtension(string $name, string $class, string $resource, string $attribute, string $owner, string $ownerClass): self
    {
        return self::taken($name, $class, $resource, $attribute, "the [{$owner}] extension {$ownerClass}");
    }

    public static function unsupportedStringList(string $name, string $class, string $resource, string $attribute, string $engine): self
    {
        return self::about($name, "The [{$name}] extension {$class} declares for {$resource} the list attribute [{$attribute}], but the engine [{$engine}] compares single values only. Match lists of strings on Algolia, Meilisearch or Typesense, or declare a single-valued attribute.");
    }

    public static function conflictingProfiles(string $engine, string $resource, string $first, string $firstClass, string $second, string $secondClass): self
    {
        return self::about($second, "The [{$first}] extension {$firstClass} and the [{$second}] extension {$secondClass} both provide the engine profile of [{$engine}] for {$resource}. Let one of them provide it: return null from engineProfile() of the other for this engine, or disable one of them in rest.extensions.");
    }

    private static function taken(string $name, string $class, string $resource, string $attribute, string $owner): self
    {
        return self::about($name, "The [{$name}] extension {$class} declares for {$resource} the index attribute [{$attribute}], which {$owner} already declares with another field type or list marker. Index attributes keep their real name: declare it the same way, index it under another name for one of them, or leave {$resource} out of the extension with appliesTo().");
    }
}

<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\TextTarget;

final readonly class ExtensionAttributes
{
    /**
     * @param  array<string, IndexAttribute>  $filterable
     * @param  array<string, IndexAttribute>  $perimeter
     * @return array<string, IndexAttribute>
     */
    public static function of(TextTarget $target, EngineProfile $profile, array $filterable, array $perimeter): array
    {
        $attributes = [];
        $owners = [];

        foreach ($target->extensions->declarations() as $name => [$class, $declared]) {
            $declarer = IndexDeclarer::extension($target->resource, $name, $class);
            $own = [];

            foreach (array_values($declared) as $index => $entry) {
                $attribute = self::attribute($target, $profile, $declarer, $entry) ?? throw InvalidEngineExtensionException::notAnIndexAttribute($name, $class, $target->resource, $index, get_debug_type($entry));
                $attributeName = $attribute->field->name;

                if (isset($own[$attributeName])) {
                    throw InvalidEngineExtensionException::repeatedIndexAttribute($name, $class, $target->resource, $attributeName);
                }

                $owner = match (true) {
                    isset($filterable[$attributeName]) => 'the filterable() list of its ScoutEngine',
                    isset($perimeter[$attributeName]) => 'the perimeter() list of its ScoutEngine',
                    isset($owners[$attributeName]) => $owners[$attributeName],
                    default => null,
                };

                if ($owner !== null) {
                    throw InvalidEngineExtensionException::indexAttributeTaken($name, $class, $target->resource, $attributeName, $owner);
                }

                $own[$attributeName] = true;
                $attributes[$attributeName] = $attribute;
            }

            foreach (array_keys($own) as $attributeName) {
                $owners[$attributeName] = "the [{$name}] extension {$class}";
            }
        }

        return $attributes;
    }

    private static function attribute(TextTarget $target, EngineProfile $profile, IndexDeclarer $declarer, mixed $entry): ?IndexAttribute
    {
        if (! $entry instanceof Field) {
            return null;
        }

        $field = $entry->boundTo($target->model);

        return IndexAttributeRules::field($field->isInputOnly() ? $field : (clone $field)->selectable(), $profile, $declarer);
    }
}

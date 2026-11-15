<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\MatchesStringLists;
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

        foreach ($target->extensions->declarations($profile) as $name => [$class, $declared]) {
            $declarer = IndexDeclarer::extension($target->resource, $name, $class);
            $own = [];

            foreach (array_values($declared) as $index => $entry) {
                if ($entry instanceof StringListAttribute && ! $profile instanceof MatchesStringLists) {
                    throw InvalidEngineExtensionException::unsupportedStringList($name, $class, $target->resource, $entry->name, $profile->name());
                }

                $attribute = self::attribute($target, $profile, $declarer, $entry) ?? throw InvalidEngineExtensionException::notAnIndexAttribute($name, $class, $target->resource, $index, get_debug_type($entry));
                $attributeName = $attribute->field->name;

                if (isset($own[$attributeName])) {
                    throw InvalidEngineExtensionException::repeatedIndexAttribute($name, $class, $target->resource, $attributeName);
                }

                $own[$attributeName] = true;

                if (self::shared($target, $name, $class, $attribute, $filterable, $perimeter, $attributes, $owners)) {
                    continue;
                }

                $attributes[$attributeName] = $attribute;
                $owners[$attributeName] = [$name, $class];
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, IndexAttribute>  $filterable
     * @param  array<string, IndexAttribute>  $perimeter
     * @param  array<string, IndexAttribute>  $attributes
     * @param  array<string, array{string, string}>  $owners
     */
    private static function shared(TextTarget $target, string $name, string $class, IndexAttribute $attribute, array $filterable, array $perimeter, array $attributes, array $owners): bool
    {
        $attributeName = $attribute->field->name;

        if (isset($filterable[$attributeName])) {
            throw InvalidEngineExtensionException::indexAttributeTakenByResource($name, $class, $target->resource, $attributeName, ResourceIndexList::Filterable);
        }

        if (isset($perimeter[$attributeName]) && ! $perimeter[$attributeName]->sameAs($attribute)) {
            throw InvalidEngineExtensionException::indexAttributeTakenByResource($name, $class, $target->resource, $attributeName, ResourceIndexList::Perimeter);
        }

        if (isset($attributes[$attributeName]) && ! $attributes[$attributeName]->sameAs($attribute)) {
            throw InvalidEngineExtensionException::indexAttributeTakenByExtension($name, $class, $target->resource, $attributeName, ...$owners[$attributeName]);
        }

        return isset($perimeter[$attributeName]) || isset($attributes[$attributeName]);
    }

    private static function attribute(TextTarget $target, EngineProfile $profile, IndexDeclarer $declarer, mixed $entry): ?IndexAttribute
    {
        if ($entry instanceof StringListAttribute) {
            return IndexAttributeRules::stringList($entry, $target->model, $profile, $declarer);
        }

        if (! $entry instanceof Field) {
            return null;
        }

        $field = $entry->boundTo($target->model);

        return IndexAttributeRules::field($field->isInputOnly() ? $field : (clone $field)->selectable(), $profile, $declarer);
    }
}

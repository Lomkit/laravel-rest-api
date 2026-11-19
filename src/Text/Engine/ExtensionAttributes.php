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
     * @param  array<string, IndexAttribute>  $indexed
     * @return array<string, IndexAttribute>
     */
    public static function of(TextTarget $target, EngineProfile $profile, array $filterable, array $perimeter, array $indexed = []): array
    {
        $attributes = [];
        $owners = [];

        foreach ($target->extensions->declarations($profile) as $name => [$class, $declared]) {
            $declarer = IndexDeclarer::extension($target->resource, $name, $class);
            $own = [];

            foreach (array_values($declared) as $index => $entry) {
                $attribute = self::attribute($target, $profile, $declarer, $entry) ?? throw InvalidEngineExtensionException::notAnIndexAttribute($name, $class, $target->resource, $index, get_debug_type($entry));
                $attributeName = $attribute->field->name;

                if (isset($own[$attributeName])) {
                    throw InvalidEngineExtensionException::repeatedIndexAttribute($name, $class, $target->resource, $attributeName);
                }

                $own[$attributeName] = true;

                if (isset($filterable[$attributeName])) {
                    throw InvalidEngineExtensionException::indexAttributeTakenByResource($name, $class, $target->resource, $attributeName, ResourceIndexList::Filterable);
                }

                self::ensureAlike($target, $name, $class, $attribute, $perimeter, ResourceIndexList::Perimeter);
                self::ensureAlike($target, $name, $class, $attribute, $indexed, ResourceIndexList::IndexAttributes);

                if (self::shared($target, $name, $class, $attribute, $attributes, $owners) || isset($perimeter[$attributeName]) || isset($indexed[$attributeName])) {
                    continue;
                }

                $attributes[$attributeName] = $attribute;
                $owners[$attributeName] = [$name, $class];
            }
        }

        return $attributes;
    }

    /** @param array<string, IndexAttribute> $resource */
    private static function ensureAlike(TextTarget $target, string $name, string $class, IndexAttribute $attribute, array $resource, ResourceIndexList $list): void
    {
        $declared = $resource[$attribute->field->name] ?? null;

        if ($declared !== null && ! $declared->sameAs($attribute)) {
            throw InvalidEngineExtensionException::indexAttributeTakenByResource($name, $class, $target->resource, $attribute->field->name, $list);
        }
    }

    /**
     * @param  array<string, IndexAttribute>  $attributes
     * @param  array<string, array{string, string}>  $owners
     */
    private static function shared(TextTarget $target, string $name, string $class, IndexAttribute $attribute, array $attributes, array $owners): bool
    {
        $attributeName = $attribute->field->name;

        if (isset($attributes[$attributeName]) && ! $attributes[$attributeName]->sameAs($attribute)) {
            throw InvalidEngineExtensionException::indexAttributeTakenByExtension($name, $class, $target->resource, $attributeName, ...$owners[$attributeName]);
        }

        return isset($attributes[$attributeName]);
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

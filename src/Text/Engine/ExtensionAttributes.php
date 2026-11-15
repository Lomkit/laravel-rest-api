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

                $list = match (true) {
                    isset($filterable[$attributeName]) => ResourceIndexList::Filterable,
                    isset($perimeter[$attributeName]) => ResourceIndexList::Perimeter,
                    default => null,
                };

                if ($list !== null) {
                    throw InvalidEngineExtensionException::indexAttributeTakenByResource($name, $class, $target->resource, $attributeName, $list);
                }

                if (isset($owners[$attributeName])) {
                    throw InvalidEngineExtensionException::indexAttributeTakenByExtension($name, $class, $target->resource, $attributeName, ...$owners[$attributeName]);
                }

                $own[$attributeName] = true;
                $attributes[$attributeName] = $attribute;
            }

            foreach (array_keys($own) as $attributeName) {
                $owners[$attributeName] = [$name, $class];
            }
        }

        return $attributes;
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

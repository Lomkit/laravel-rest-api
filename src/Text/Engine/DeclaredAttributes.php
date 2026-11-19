<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\Engine\Profiles\ReservesSortAttributes;
use Lomkit\Rest\Text\TextTarget;

/** @internal */
final readonly class DeclaredAttributes
{
    /**
     * @param  array<mixed>  $declared
     * @return array<string, IndexAttribute>
     */
    public static function of(TextTarget $target, EngineProfile $profile, array $declared, string $declaration, bool $restricted = false, bool $lists = false): array
    {
        $attributes = [];
        $declarer = IndexDeclarer::resource($target->resource);

        foreach (array_values($declared) as $index => $attribute) {
            $entry = match (true) {
                $lists && $attribute instanceof StringListAttribute => $attribute,
                $attribute instanceof Field => $attribute->boundTo($target->model),
                is_string($attribute) => $target->declared->find($attribute) ?? throw InvalidTextSearchException::unknownAttribute($target->resource, $attribute),
                default => throw InvalidTextSearchException::notAnAttribute($target->resource, $declaration, $index, $attribute),
            };

            if (isset($attributes[$entry->name])) {
                throw InvalidTextSearchException::duplicateAttribute($target->resource, $entry->name);
            }

            $attributes[$entry->name] = $entry instanceof StringListAttribute
                ? IndexAttributeRules::stringList($entry, $target->model, $profile, $declarer)
                : IndexAttributeRules::field($restricted && ! $entry->isInputOnly() ? (clone $entry)->selectable() : $entry, $profile, $declarer);
        }

        return $attributes;
    }

    /**
     * @param  array<string, IndexAttribute>  $filterable
     * @param  array<mixed>  $sortable
     * @return list<string>
     */
    public static function sorts(TextTarget $target, EngineProfile $profile, array $filterable, array $sortable, bool $lists = false): array
    {
        $sorts = [];

        foreach (array_values($sortable) as $index => $name) {
            if (! is_string($name)) {
                throw InvalidTextSearchException::notAnAttributeName($target->resource, $index, $name);
            }

            if (! isset($filterable[$name])) {
                throw InvalidTextSearchException::unsortableAttribute($target->resource, $name);
            }

            if (in_array($name, $sorts, true)) {
                throw InvalidTextSearchException::duplicateAttribute($target->resource, $name);
            }

            self::ensureSortable($name, $target->resource, $profile);

            $sorts[] = $name;
        }

        if ($sorts !== [] && $profile->sortKeys() === 0 && ! ($lists && $profile instanceof ListsWithoutText && $profile->listSortKeys() > 0)) {
            throw InvalidTextSearchException::unsortableEngine($target->resource, $profile->name());
        }

        return $sorts;
    }

    /** @param class-string<Resource> $resource */
    public static function ensureSortable(string $attribute, string $resource, EngineProfile $profile): void
    {
        if ($profile instanceof ReservesSortAttributes && in_array($attribute, $profile->reservedSorts(), true)) {
            throw InvalidTextSearchException::reservedSortAttribute($resource, $attribute, $profile->name());
        }
    }
}

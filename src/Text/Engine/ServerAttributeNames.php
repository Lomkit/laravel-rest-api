<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Fields\Field;

/** @internal */
final readonly class ServerAttributeNames
{
    /**
     * @param  array<mixed>  $attributes
     * @return list<string>
     */
    public static function of(array $attributes): array
    {
        $names = array_map(static fn (mixed $attribute): ?string => match (true) {
            $attribute instanceof StringListAttribute => null,
            $attribute instanceof Field => $attribute->name,
            is_string($attribute) => $attribute,
            default => null,
        }, array_values($attributes));

        return array_values(array_filter($names, is_string(...)));
    }
}

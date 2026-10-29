<?php

namespace Lomkit\Rest\Concerns\Resource;

trait ForgetsRequestState
{
    /** @return list<string> */
    public function __sleep(): array
    {
        return array_values(array_filter(
            array_keys((array) $this),
            fn (string $property): bool => ! in_array(self::unmangledName($property), ['calculatedFields', 'calculatedScopes'], true),
        ));
    }

    private static function unmangledName(string $property): string
    {
        $separator = strrpos($property, "\0");

        return $separator === false ? $property : substr($property, $separator + 1);
    }
}

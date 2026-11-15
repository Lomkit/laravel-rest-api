<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;

final readonly class EngineOptions
{
    /**
     * @param  array<string, mixed>  $current
     * @param  array<mixed>  $options
     * @param  list<string>  $owned
     * @return array<string, mixed>
     */
    public static function merge(array $current, array $options, array $owned): array
    {
        $merged = $current;

        foreach ($options as $key => $value) {
            if (! is_string($key)) {
                throw InvalidEngineQueryException::invalidOption($key);
            }

            if (in_array($key, $owned, true)) {
                throw InvalidEngineQueryException::ownedOption($key);
            }

            if (array_key_exists($key, $merged) && $merged[$key] !== $value) {
                throw InvalidEngineQueryException::conflictingOption($key);
            }

            $merged[$key] = $value;
        }

        return $merged;
    }
}

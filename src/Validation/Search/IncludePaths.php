<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Validation\Cursor;

final readonly class IncludePaths
{
    /**
     * @param  list<Cursor>  $entries
     * @return array<string, true>
     */
    public static function repeated(array $entries, int $levels): array
    {
        $owners = [];

        foreach ($entries as $entry) {
            foreach (self::paths($entry, $levels) as $path) {
                $owners[$path][$entry->path] = true;
            }
        }

        $repeated = [];

        foreach ($owners as $entryPaths) {
            if (count($entryPaths) > 1) {
                $repeated += $entryPaths;
            }
        }

        return $repeated;
    }

    /** @return list<string> */
    private static function paths(Cursor $entry, int $levels): array
    {
        $relation = $entry->at('relation')->value;

        if (! is_string($relation) || $entry->at('alias')->present) {
            return [];
        }

        $paths = [$relation];
        $left = $levels - count(explode('.', $relation));

        foreach ($left > 0 ? self::children($entry->at('includes')) : [] as $child) {
            foreach (self::paths($child, $left) as $path) {
                $paths[] = $relation.'.'.$path;
            }
        }

        return $paths;
    }

    /** @return list<Cursor> */
    private static function children(Cursor $includes): array
    {
        if (! is_array($includes->value) || ! array_is_list($includes->value)) {
            return [];
        }

        return array_map($includes->item(...), array_keys($includes->value));
    }
}

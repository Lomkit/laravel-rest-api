<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class LinkedKeys
{
    private const int CHUNK = 1000;

    /**
     * @param  BelongsToMany<Model, Model>  $pivots
     * @param  list<int|string>  $keys
     * @return list<int|string>
     */
    public static function among(BelongsToMany $pivots, array $keys): array
    {
        $linked = [];

        foreach (array_chunk($keys, self::CHUNK) as $chunk) {
            $found = $pivots
                ->newPivotQuery()
                ->whereIn($pivots->getQualifiedRelatedPivotKeyName(), $chunk)
                ->pluck($pivots->getRelatedPivotKeyName())
                ->all();
            $found = array_filter($found, fn (mixed $key): bool => is_int($key) || is_string($key));
            $linked = [...$linked, ...array_intersect($chunk, $found)];
        }

        return $linked;
    }

    /**
     * @param  list<int|string>  $keys
     * @param  list<int|string>  $linked
     * @return list<int|string>
     */
    public static function missing(array $keys, array $linked): array
    {
        return array_values(array_diff($keys, $linked));
    }
}

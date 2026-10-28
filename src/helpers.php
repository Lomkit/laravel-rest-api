<?php

use Illuminate\Support\Str;

if (! function_exists('relation_without_pivot')) {
    /**
     * Remove the '.pivot.' portion from a relation name, if present.
     *
     *
     * @return string
     */
    function relation_without_pivot(string $relation)
    {
        return Str::contains($relation, '.pivot.') || Str::endsWith($relation, '.pivot') ?
            Str::replaceLast('.pivot', '', $relation) :
            $relation;
    }
}

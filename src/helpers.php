<?php

if (!function_exists('relation_without_pivot')) {
    /**
     * Remove the '.pivot.' portion from a relation name, if present.
     *
     * @param string $relation
     *
     * @return string
     */
    function relation_without_pivot(string $relation)
    {
        return \Illuminate\Support\Str::contains($relation, '.pivot.') ?
            \Illuminate\Support\Str::replaceLast('pivot.', '', $relation) :
            $relation;
    }
}

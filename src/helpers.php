<?php

if (! function_exists('relation_without_pivot')) {
    function relation_without_pivot(string $relation)
    {
        return \Illuminate\Support\Str::contains($relation, '.pivot.') ?
            \Illuminate\Support\Str::replaceLast('pivot.', '', $relation):
            $relation;

    }
}
<?php

namespace Lomkit\Rest\Concerns;


trait Statsable
{
    /**
     * Custom stats resource.
     *
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     *
     * @return array
     */
    public function stats(\Illuminate\Contracts\Database\Eloquent\Builder $query): array
    {
        return [];
    }
}

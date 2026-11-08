<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Resource;

abstract class TeamResource extends Resource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('team_id', 7);
    }
}

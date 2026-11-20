<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;

class PerimeterShowResource extends ShowResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('level', '!=', 2);
    }
}

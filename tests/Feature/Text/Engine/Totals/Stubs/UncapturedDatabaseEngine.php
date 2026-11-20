<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\DatabaseEngine;

class UncapturedDatabaseEngine extends DatabaseEngine
{
    /**
     * @param  Builder<Model>  $builder
     * @return LengthAwarePaginator<int, Model>
     */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        return new LengthAwarePaginator([], 0, (int) $perPage, (int) $page);
    }
}

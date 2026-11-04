<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Query\PageQuery;

trait Paginable
{
    /**
     * @param  Builder<Model>  $query
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(Builder $query, int $perPage, int $page): LengthAwarePaginator
    {
        return (new PageQuery($query))->paginate($perPage, $page);
    }
}

<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Query\PageQuery;

trait Paginable
{
    /** @return list<int> */
    public function limits(): array
    {
        return [
            10,
            25,
            50,
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(Builder $query, int $perPage, int $page): LengthAwarePaginator
    {
        $maxTotal = $this->definition()
            ->limits()
            ->maxTotal;

        return (new PageQuery($query, $maxTotal))->paginate($perPage, $page);
    }
}

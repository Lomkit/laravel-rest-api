<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

final readonly class PageQuery
{
    /** @param Builder<Model> $query */
    public function __construct(private Builder $query) {}

    /** @return LengthAwarePaginator<int, Model> */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        $rows = $this->query
            ->clone()
            ->forPage($page, $perPage)
            ->get();

        return new LengthAwarePaginator($rows, $this->total($rows->count(), $perPage, $page), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    private function total(int $count, int $perPage, int $page): int
    {
        if ($count === $perPage || ($count === 0 && $page > 1)) {
            return $this->query->toBase()->getCountForPagination();
        }

        return ($page - 1) * $perPage + $count;
    }
}

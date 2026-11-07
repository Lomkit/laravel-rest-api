<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Lomkit\Rest\Pagination\CappedPaginator;

final readonly class PageQuery
{
    /** @param Builder<Model> $query */
    public function __construct(
        private Builder $query,
        private ?int $maxTotal = null,
    ) {}

    /** @return LengthAwarePaginator<int, Model> */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        $rows = $this->query
            ->clone()
            ->forPage($page, $perPage)
            ->get();
        $seen = $rows->isEmpty() ? 0 : ($page - 1) * $perPage + $rows->count();
        $counts = $rows->count() === $perPage || ($rows->isEmpty() && $page > 1);
        $options = [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ];

        if ($this->maxTotal === null) {
            $total = $counts ? $this->query->toBase()->getCountForPagination() : $seen;

            return new LengthAwarePaginator($rows, $total, $perPage, $page, $options);
        }

        $count = $counts ? $this->cappedCount($this->maxTotal) : $seen;
        $capped = $counts && $count > $this->maxTotal;
        $total = $capped ? max($this->maxTotal, $seen) : $count;

        return new CappedPaginator($rows, $total, $perPage, $page, $options, $capped);
    }

    private function cappedCount(int $maxTotal): int
    {
        $rows = $this->countedRows();

        return $rows->newQuery()
            ->fromSub($rows->limit($maxTotal + 1), 'capped')
            ->count();
    }

    private function countedRows(): QueryBuilder
    {
        $base = $this->query->toBase();

        if ($base->groups !== null || $base->havings !== null) {
            return $base
                ->cloneWithout(['orders', 'limit', 'offset'])
                ->cloneWithoutBindings(['order']);
        }

        return $base
            ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order'])
            ->select($this->query->getModel()->getQualifiedKeyName());
    }
}

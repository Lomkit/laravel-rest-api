<?php

namespace Lomkit\Rest\Pagination;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/** @extends LengthAwarePaginator<int, Model> */
final class CappedPaginator extends LengthAwarePaginator
{
    /**
     * @param  Collection<int, Model>  $items
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        Collection $items,
        int $total,
        int $perPage,
        int $currentPage,
        array $options,
        public readonly bool $totalCapped,
    ) {
        parent::__construct($items, $total, $perPage, $currentPage, $options);
    }
}

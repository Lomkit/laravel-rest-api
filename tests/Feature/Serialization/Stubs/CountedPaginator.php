<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * @extends Paginator<int, Model>
 *
 * @implements LengthAwarePaginator<int, Model>
 */
final class CountedPaginator extends Paginator implements LengthAwarePaginator
{
    /** @param Collection<int, Model> $items */
    public function __construct(Collection $items, private readonly int $total, int $perPage, int $currentPage)
    {
        parent::__construct($items, $perPage, $currentPage);
    }

    #[\Override]
    public function total(): int
    {
        return $this->total;
    }

    #[\Override]
    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}

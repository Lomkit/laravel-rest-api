<?php

namespace Lomkit\Rest\Pagination;

use Illuminate\Pagination\LengthAwarePaginator as BaseLengthAwarePaginator;

/** @extends BaseLengthAwarePaginator<int, array<string, mixed>> */
class LengthAwarePaginator extends BaseLengthAwarePaginator
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        array $items,
        int $total,
        int $perPage,
        int $currentPage,
        array $options = [],
        private readonly array $meta = [],
        private readonly ?bool $totalCapped = null,
    ) {
        parent::__construct($items, $total, $perPage, $currentPage, $options);
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }

    /** @return array<array-key, mixed> */
    #[\Override]
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'per_page' => $this->perPage(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
            ...($this->totalCapped === null ? [] : ['total_capped' => $this->totalCapped]),
            'meta' => $this->meta(),
        ];
    }
}

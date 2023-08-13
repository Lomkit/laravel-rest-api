<?php

namespace Lomkit\Rest\Pagination;

use Illuminate\Support\Collection;
use \Illuminate\Pagination\LengthAwarePaginator as BaseLengthAwarePaginator;

class LengthAwarePaginator extends BaseLengthAwarePaginator
{
    /**
     * The meta returned by the api.
     *
     * @var array
     */
    protected array $meta;

    /**
     * Create a new paginator instance.
     *
     * @param  mixed  $items
     * @param  int  $total
     * @param  int  $perPage
     * @param  int|null  $currentPage
     * @param  array  $options  (path, query, fragment, pageName)
     * @return void
     */
    public function __construct($items, $total, $perPage, $currentPage = null, array $options = [], $meta = [])
    {
        parent::__construct($items, $total, $perPage, $currentPage, $options);

        $this->meta = $meta;
    }

    /**
     * Get the meta of items being paginated.
     *
     * @return array
     */
    public function meta()
    {
        return $this->meta;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'per_page' => $this->perPage(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
            'meta' => $this->meta()
        ];
    }
}

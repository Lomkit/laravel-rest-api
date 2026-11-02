<?php

namespace Lomkit\Rest\Http\Serialization;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Pagination\LengthAwarePaginator as RestLengthAwarePaginator;

final readonly class PageEnvelope implements Envelope
{
    /** @param LengthAwarePaginator<int, Model> $page */
    public function __construct(private LengthAwarePaginator $page) {}

    #[\Override]
    public function wrap(Closure $row, array $meta): array
    {
        $page = new RestLengthAwarePaginator(
            array_map($row, $this->page->items()),
            $this->page->total(),
            $this->page->perPage(),
            $this->page->currentPage(),
            [],
            $meta,
        );

        return $page->toArray();
    }
}

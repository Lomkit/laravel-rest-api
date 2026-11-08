<?php

namespace Lomkit\Rest\Search\Run;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Response;
use Lomkit\Rest\Search\Plan\IncludeOverflow;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class SearchedPage
{
    /**
     * @param  LengthAwarePaginator<int, Model>  $rows
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public QueryPlan $plan,
        public LengthAwarePaginator $rows,
        public IncludeOverflow $overflow,
        public array $meta,
    ) {}

    public function response(): Response
    {
        return $this->plan->resource::newResponse()
            ->plan($this->plan)
            ->responsable($this->rows)
            ->overflow($this->overflow)
            ->meta($this->meta);
    }
}

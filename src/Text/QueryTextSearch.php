<?php

namespace Lomkit\Rest\Text;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\SortDirection;

interface QueryTextSearch extends TextSearch
{
    /** @param Builder<Model> $query */
    public function apply(Builder $query, TextQuery $text, TextTarget $target): void;

    /** @param Builder<Model> $query */
    public function orderByRelevance(Builder $query, TextQuery $text, TextTarget $target, SortDirection $direction): void;

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $query
     * @return TResult
     */
    public function run(Closure $query, TextTarget $target): mixed;
}

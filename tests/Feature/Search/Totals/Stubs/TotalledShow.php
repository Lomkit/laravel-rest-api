<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals\Stubs;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Festival\OpenFestivalPolicy;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;

#[Table('shows')]
#[UsePolicy(OpenFestivalPolicy::class)]
class TotalledShow extends Show
{
    /** @param Builder<self> $query */
    #[Scope]
    protected function commentedTwice(Builder $query): void
    {
        $query
            ->join('comments', 'comments.show_id', '=', 'shows.id')
            ->groupBy('shows.id')
            ->havingRaw('count(comments.id) >= 2');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function commented(Builder $query): void
    {
        $query->join('comments', 'comments.show_id', '=', 'shows.id');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function distinctlyCommented(Builder $query): void
    {
        $query
            ->select('shows.*')
            ->distinct()
            ->join('comments', 'comments.show_id', '=', 'shows.id');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function withHeadliners(Builder $query): void
    {
        $query->unionAll(self::query()->select('shows.*')->where('level', 3));
    }
}

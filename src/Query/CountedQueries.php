<?php

namespace Lomkit\Rest\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WeakMap;

/** @internal */
final class CountedQueries
{
    /** @var WeakMap<Builder<Model>, array{columns: array<Expression|string>|null, select: list<mixed>}>|null */
    private static ?WeakMap $planned = null;

    /** @param Builder<Model> $query */
    public static function remember(Builder $query): void
    {
        $base = $query->getQuery();
        self::$planned ??= new WeakMap;
        self::$planned[$query] = ['columns' => $base->columns, 'select' => $base->bindings['select']];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function withoutAdditions(Builder $query): Builder
    {
        $planned = self::$planned[$query] ?? null;

        if ($planned === null) {
            return $query;
        }

        $counted = $query->clone();
        $counted->getQuery()->columns = $planned['columns'];
        $counted->getQuery()->bindings['select'] = $planned['select'];

        return $counted;
    }
}

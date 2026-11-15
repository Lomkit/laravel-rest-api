<?php

namespace Lomkit\Rest\Query;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WeakMap;

/** @internal */
final class CountedQueries
{
    /** @var WeakMap<Builder<Model>, AddedSelection>|null */
    private static ?WeakMap $augmented = null;

    /**
     * @param  Builder<Model>  $query
     * @param  Closure(): void  $augment
     */
    public static function augment(Builder $query, Closure $augment): void
    {
        $before = $query->getQuery();
        $columns = $before->columns;
        $bindings = $before->bindings['select'];

        $augment();

        $added = AddedSelection::between($columns, $bindings, $query->getQuery());

        if ($added === null) {
            return;
        }

        self::track($query, $added);
        $query->onClone(static function (Builder $clone) use ($added): void {
            self::track($clone, $added);
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function withoutAdditions(Builder $query): Builder
    {
        $added = self::$augmented[$query] ?? null;

        if ($added === null) {
            return $query;
        }

        $counted = $query->clone();

        return $added->removeFrom($counted->getQuery()) ? $counted : $query;
    }

    /** @param Builder<Model> $query */
    private static function track(Builder $query, AddedSelection $added): void
    {
        self::$augmented ??= new WeakMap;
        self::$augmented[$query] = $added;
    }
}

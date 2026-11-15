<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Text\Engine\RowRestriction;

/** @internal */
final readonly class CollectionCallback
{
    /** @param Builder<Model> $builder */
    public static function restrict(Builder $builder, RowRestriction $rows): void
    {
        $builder->callback = static function (EloquentBuilder $query, Builder $builder) use ($rows): void {
            self::conditions($query, $builder);
            $rows->apply($query);
        };
    }

    /**
     * @param  EloquentBuilder<Model>  $query
     * @param  Builder<Model>  $builder
     */
    private static function conditions(EloquentBuilder $query, Builder $builder): void
    {
        foreach ($builder->wheres as $where) {
            if (is_array($where) && is_string($where['field'] ?? null) && $where['field'] !== '__soft_deleted') {
                $query->where($where['field'], $where['operator'] ?? '=', $where['value'] ?? null);
            }
        }

        foreach ($builder->whereIns as $field => $values) {
            $query->whereIn((string) $field, is_array($values) ? $values : []);
        }

        foreach ($builder->whereNotIns as $field => $values) {
            $query->whereNotIn((string) $field, is_array($values) ? $values : []);
        }
    }
}

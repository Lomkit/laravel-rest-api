<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class AddedColumns
{
    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $columns
     */
    public static function merge(Builder $query, array $columns): void
    {
        $base = $query->getQuery();
        $selected = $base->columns;

        if ($columns === [] || $selected === null) {
            return;
        }

        $model = $query->getModel();
        $every = ['*', $model->qualifyColumn('*')];

        if (array_any($selected, static fn (mixed $entry): bool => in_array($entry, $every, true))) {
            return;
        }

        $listed = array_filter($selected, is_string(...));
        $missing = array_diff(array_unique(array_map($model->qualifyColumn(...), $columns)), $listed);

        if ($missing !== []) {
            $base->columns = [...$selected, ...array_values($missing)];
        }
    }
}

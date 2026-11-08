<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Query\Clauses\Clause;

final readonly class IncludeLevels
{
    /**
     * @param  list<Model>  $models
     * @return list<array{QueryPlan, list<Model>}>
     */
    public static function of(Clause $include, array $models): array
    {
        return match (true) {
            $include instanceof AliasedIncludeClause => [[$include->plan, self::related($models, $include->alias)]],
            $include instanceof IncludeClause => [[$include->plan, self::related($models, $include->relation)]],
            $include instanceof MorphToIncludeClause => self::branches($include, self::related($models, $include->name())),
            default => [],
        };
    }

    /**
     * @param  list<Model>  $rows
     * @return list<array{QueryPlan, list<Model>}>
     */
    private static function branches(MorphToIncludeClause $include, array $rows): array
    {
        $types = [];

        foreach ($rows as $row) {
            $types[$row->getMorphClass()][] = $row;
        }

        $levels = [];

        foreach (array_intersect_key($types, $include->branches) as $type => $typed) {
            $levels[] = [$include->branches[$type], $typed];
        }

        return $levels;
    }

    /**
     * @param  list<Model>  $models
     * @return list<Model>
     */
    private static function related(array $models, string $name): array
    {
        $rows = [];

        foreach ($models as $model) {
            $value = $model->relationLoaded($name) ? $model->getRelation($name) : null;

            if ($value instanceof Model) {
                $rows[] = $value;
            } elseif ($value instanceof Collection) {
                $rows = [...$rows, ...array_values($value->ensure(Model::class)->all())];
            }
        }

        return $rows;
    }
}

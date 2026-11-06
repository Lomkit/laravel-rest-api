<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Search\GateAbility;
use Lomkit\Rest\Search\Plan\AliasedIncludeClause;
use Lomkit\Rest\Search\Plan\IncludeClause;
use Lomkit\Rest\Search\Plan\MorphToIncludeClause;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class GatePrefetch
{
    /**
     * @param  list<Model>  $models
     * @return list<Check>
     */
    public static function page(QueryPlan $plan, array $models, GateReach $reach): array
    {
        $subject = $plan->resource::newModel()::class;
        $checks = array_map(static fn (GateAbility $gate): Check => new Check($plan->resource, $gate->name(), $subject), self::gates($plan, true));

        return [...$checks, ...self::rows($plan, $models, $reach)];
    }

    /**
     * @param  list<Model>  $models
     * @return list<Check>
     */
    public static function rows(QueryPlan $plan, array $models, GateReach $reach): array
    {
        $checks = [];

        foreach (self::gates($plan, false) as $gate) {
            foreach ($models as $model) {
                if ($reach->reaches($gate, $model)) {
                    $checks[] = new Check($plan->resource, $gate->name(), $model);
                }
            }
        }

        foreach ($plan->includes as $include) {
            foreach (self::levels($include, $models) as [$level, $rows]) {
                $checks = [...$checks, ...self::rows($level, $rows, GateReach::everyRow())];
            }
        }

        return $checks;
    }

    /** @return list<GateAbility> */
    private static function gates(QueryPlan $plan, bool $classLevel): array
    {
        $resource = $plan->resource;

        if (! $resource->isGatingEnabled() || ! $resource->isAuthorizationCacheEnabled()) {
            return [];
        }

        $abilities = $resource->definition()->cachedAbilities();

        return array_values(array_filter(
            $plan->gates ?? [],
            static fn (GateAbility $gate): bool => $gate->isClassLevel() === $classLevel && $abilities->seconds($gate->name()) !== null,
        ));
    }

    /**
     * @param  list<Model>  $models
     * @return list<array{QueryPlan, list<Model>}>
     */
    private static function levels(Clause $include, array $models): array
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

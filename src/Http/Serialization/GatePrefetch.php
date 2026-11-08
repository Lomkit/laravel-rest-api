<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Search\GateAbility;
use Lomkit\Rest\Search\Plan\IncludeLevels;
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
            foreach (IncludeLevels::of($include, $models) as [$level, $rows]) {
                $checks = [...$checks, ...self::rows($level, $rows, GateReach::everyRow())];
            }
        }

        return $checks;
    }

    /** @return list<GateAbility> */
    private static function gates(QueryPlan $plan, bool $classLevel): array
    {
        $resource = $plan->resource;

        if (! $resource->isGatingEnabled() || ! $resource->context()->config()->cacheEnabled) {
            return [];
        }

        $abilities = $resource->definition()->cachedAbilities();

        return array_values(array_filter(
            $plan->gates ?? [],
            static fn (GateAbility $gate): bool => $gate->isClassLevel() === $classLevel && $abilities->seconds($gate->name()) !== null,
        ));
    }
}

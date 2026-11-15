<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Extensions\Contexts\AugmentationContext;
use Lomkit\Rest\Extensions\Kinds\AugmentsQueries;
use Lomkit\Rest\Query\CountedQueries;
use Lomkit\Rest\Query\RelationAggregate;
use Lomkit\Rest\Search\GateAbility;

/** @internal */
final readonly class PlanAugmentation
{
    public static function apply(QueryPlan $plan, Builder $query): void
    {
        if ($plan->rendered === null) {
            return;
        }

        $resource = $plan->resource;
        $extensions = $resource->context()->extensions()->applicable($resource::class, AugmentsQueries::class);

        if ($extensions === []) {
            return;
        }

        $eloquent = RelationAggregate::eloquent($query);
        $additions = new QueryAdditions($resource, $plan->rendered);
        $user = $resource->context()->actingUser()->user;
        $gates = self::gates($plan);

        foreach ($extensions as $name => $extension) {
            $extension->augment(new AugmentationContext($name, $extension::class, $resource, $user, $gates, $additions));
        }

        $additions->seal();

        if ($query instanceof Relation) {
            $additions->applyTo($eloquent);

            return;
        }

        CountedQueries::augment($eloquent, static function () use ($additions, $eloquent): void {
            $additions->applyTo($eloquent);
        });
    }

    /** @return list<string> */
    private static function gates(QueryPlan $plan): array
    {
        if (! $plan->resource->isGatingEnabled()) {
            return [];
        }

        $gates = array_filter($plan->gates ?? [], static fn (GateAbility $gate): bool => ! $gate->isClassLevel());

        return array_values(array_map(static fn (GateAbility $gate): string => $gate->name(), $gates));
    }
}

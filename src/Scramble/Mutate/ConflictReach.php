<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;

final class ConflictReach
{
    public static function of(Resource $resource): bool
    {
        $level = [[$resource, true]];
        $seen = [];
        $depth = $resource->context()->limits()->depths->mutations;

        for ($at = 0; $level !== [] && $at <= $depth; $at++) {
            $next = [];

            foreach ($level as [$current, $updated]) {
                if (self::conflicts($current, $updated, $at < $depth)) {
                    return true;
                }

                if (isset($seen[$current::class])) {
                    continue;
                }

                $seen[$current::class] = true;
                $next = [...$next, ...self::related($current)];
            }

            $level = $next;
        }

        return false;
    }

    private static function conflicts(Resource $resource, bool $updated, bool $nests): bool
    {
        if ($updated && $resource->definition()->version() !== null) {
            return true;
        }

        return $nests && array_any($resource->definition()->relations(), fn (Relation $relation): bool => $relation->syncsChildren());
    }

    /** @return list<array{Resource, bool}> */
    private static function related(Resource $resource): array
    {
        $related = [];

        foreach ($resource->definition()->relations() as $relation) {
            $accepted = $relation->acceptedOperations();
            $operations = array_filter($accepted, fn (MutationOperation $operation): bool => $operation->takesRelations() && $relation->writesRelatedRows($operation));

            if ($operations === []) {
                continue;
            }

            $updated = in_array(MutationOperation::Update, $operations, true);

            if (! $relation instanceof PolymorphicRelation) {
                $related[] = [$relation->resource(), $updated];

                continue;
            }

            foreach ($relation->targets() as $target) {
                $related[] = [$relation->resourceOf($target), $updated];
            }
        }

        return $related;
    }
}

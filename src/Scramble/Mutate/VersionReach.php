<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;

final class VersionReach
{
    public static function of(Resource $resource): bool
    {
        $level = [[$resource, true]];
        $seen = [];
        $depth = $resource->context()->limits()->depths->mutations;

        for ($at = 0; $level !== [] && $at <= $depth; $at++) {
            $next = [];

            foreach ($level as [$current, $updated]) {
                if ($updated && $current->definition()->version() !== null) {
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

    /** @return list<array{Resource, bool}> */
    private static function related(Resource $resource): array
    {
        $related = [];

        foreach ($resource->definition()->relations() as $relation) {
            $operations = array_filter($relation->acceptedOperations(), fn (MutationOperation $operation): bool => $operation->takesRelations());

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

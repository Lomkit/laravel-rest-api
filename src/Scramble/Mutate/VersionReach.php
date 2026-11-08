<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasOneOrManyThrough;

final class VersionReach
{
    public static function of(Resource $resource): bool
    {
        $level = [$resource];
        $seen = [];
        $depth = $resource->context()->limits()->depths->mutations;

        for ($at = 0; $level !== [] && $at <= $depth; $at++) {
            $next = [];

            foreach ($level as $current) {
                if (isset($seen[$current::class])) {
                    continue;
                }

                if ($current->definition()->version() !== null) {
                    return true;
                }

                $seen[$current::class] = true;
                $next = [...$next, ...self::related($current)];
            }

            $level = $next;
        }

        return false;
    }

    /** @return list<Resource> */
    private static function related(Resource $resource): array
    {
        $related = [];

        foreach ($resource->definition()->relations() as $relation) {
            if ($relation instanceof HasOneOrManyThrough || ! in_array(MutationOperation::Update, $relation->mutationOperations(), true)) {
                continue;
            }

            if (! $relation instanceof PolymorphicRelation) {
                $related[] = $relation->resource();

                continue;
            }

            foreach ($relation->targets() as $target) {
                $related[] = $relation->resourceOf($target);
            }
        }

        return $related;
    }
}

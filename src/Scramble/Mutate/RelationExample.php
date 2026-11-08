<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scramble\Components\KeySchema;

final class RelationExample
{
    /** @return array<string, mixed>|list<array<string, mixed>> */
    public static function of(Relation $relation): array
    {
        $entry = self::entry($relation);

        return $relation->hasMultipleEntries() ? [$entry] : $entry;
    }

    /** @return array<string, mixed> */
    private static function entry(Relation $relation): array
    {
        if ($relation instanceof PolymorphicRelation && $relation->targets()->isPolymorphic()) {
            foreach ($relation->targets() as $target) {
                return self::operation($relation, $relation->resourceOf($target), ['type' => $target->type]);
            }
        }

        return self::operation($relation, $relation->resource(), []);
    }

    /**
     * @param  array<string, string>  $type
     * @return array<string, mixed>
     */
    private static function operation(Relation $relation, Resource $related, array $type): array
    {
        if (in_array(MutationOperation::Attach, $relation->acceptedOperations(), true)) {
            return ['operation' => MutationOperation::Attach->value, ...$type, 'key' => KeySchema::example($related)];
        }

        $attributes = MutationExample::attributes($related);

        return ['operation' => MutationOperation::Create->value, ...$type, ...($attributes === [] ? [] : ['attributes' => $attributes])];
    }
}

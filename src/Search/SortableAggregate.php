<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\Plan\Columns\RelationKeys;

final class SortableAggregate
{
    /** @param non-empty-list<Relation> $relations */
    public static function accepts(array $relations, ?string $field): bool
    {
        if (! array_any($relations, static fn (Relation $relation): bool => $relation->hasMultipleEntries())) {
            return false;
        }

        return $field === null || ! in_array($field, self::keys($relations[array_key_last($relations)]), true);
    }

    /** @return list<string> */
    private static function keys(Relation $relation): array
    {
        $related = $relation->resource();
        $model = $related::newModel();
        $keys = [
            $model->getKeyName(),
            ...RelationKeys::between($relation->parentResource()::newModel(), $relation->relation)->related(),
        ];

        foreach ($related->definition()->fields() as $field) {
            if ($field instanceof ID) {
                $keys[] = $field->name;
            }
        }

        foreach ($related->definition()->relations() as $own) {
            $keys = [...$keys, ...RelationKeys::between($model, $own->relation)->parent()];
        }

        return array_values(array_unique($keys));
    }
}

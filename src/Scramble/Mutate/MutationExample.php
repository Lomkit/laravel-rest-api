<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\FieldExample;

final class MutationExample
{
    /** @return array{mutate: list<array<string, mixed>>} */
    public static function of(Resource $resource): array
    {
        return ['mutate' => [self::create($resource)]];
    }

    /** @return array<string, mixed> */
    private static function create(Resource $resource): array
    {
        $entry = ['operation' => WriteOperation::Create->value];
        $attributes = self::attributes($resource);
        $relations = [];

        if ($attributes !== []) {
            $entry['attributes'] = $attributes;
        }

        foreach (RelationEntrySchema::required($resource, MutationOperation::Create) as $relation) {
            $relations[$relation->relation] = RelationExample::of($relation);
        }

        if ($relations !== []) {
            $entry['relations'] = $relations;
        }

        return $entry;
    }

    /** @return array<string, mixed> */
    public static function attributes(Resource $resource): array
    {
        $types = WritableSchema::morphTypes($resource);
        $attributes = [];
        $fields = $resource
            ->definition()
            ->fields()
            ->writableOn(WriteOperation::Create);

        foreach ($fields as $field) {
            $attributes[$field->name] = $field->getExamples() === [] && isset($types[$field->name]) ? $types[$field->name][0] : FieldExample::written($field, WriteOperation::Create);
        }

        return $attributes;
    }
}

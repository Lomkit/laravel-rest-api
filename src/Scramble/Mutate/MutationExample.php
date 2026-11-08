<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\FieldExample;

final class MutationExample
{
    /** @return array{mutate: list<array{operation: string, attributes?: array<string, mixed>}>} */
    public static function of(Resource $resource): array
    {
        $types = WritableSchema::morphTypes($resource);
        $attributes = [];
        $fields = $resource
            ->definition()
            ->fields()
            ->writableOn(WriteOperation::Create);

        foreach ($fields as $field) {
            $attributes[$field->name] = $field->getExamples() === [] && isset($types[$field->name]) ? $types[$field->name][0] : FieldExample::written($field);
        }

        $entry = ['operation' => WriteOperation::Create->value];

        if ($attributes !== []) {
            $entry['attributes'] = $attributes;
        }

        return ['mutate' => [$entry]];
    }
}

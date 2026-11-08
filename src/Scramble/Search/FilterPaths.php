<?php

namespace Lomkit\Rest\Scramble\Search;

use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;

final class FilterPaths
{
    /** @return array<string, Field> */
    public static function of(Resource $resource): array
    {
        $paths = [];

        $filterable = $resource
            ->definition()
            ->fields()
            ->filterable();

        foreach ($filterable as $field) {
            $paths[$field->name] = $field;
        }

        foreach ($resource->definition()->relations() as $relation) {
            foreach (self::candidates($resource, $relation) as $path) {
                $resolved = FieldPath::resolve($resource, $path);

                if ($resolved !== null) {
                    $paths[$path] = $resolved->field;
                }
            }
        }

        return $paths;
    }

    public static function component(string $path): string
    {
        return implode('_', array_map(fn (string $segment): string => Str::studly($segment), explode('.', $path)));
    }

    /** @return list<string> */
    private static function candidates(Resource $resource, Relation $relation): array
    {
        $related = FieldPath::relationsAlong($resource, $relation->relation.'.') === [] ? [] : $relation
            ->resource()
            ->definition()
            ->fields()
            ->filterable()
            ->names();
        $pivot = $relation instanceof PivotRelation ? $relation->getPivotFields()->names() : [];

        return [
            ...array_map(fn (string $name): string => $relation->relation.'.'.$name, $related),
            ...array_map(fn (string $name): string => $relation->relation.'.pivot.'.$name, $pivot),
        ];
    }
}

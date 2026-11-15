<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;

final readonly class SortSchema
{
    public function __construct(
        private ComponentRegistry $components,
        private RecordSchema $records,
    ) {}

    public function sort(Resource $resource, bool $root): ?Type
    {
        $sortable = $resource
            ->definition()
            ->fields()
            ->sortable();
        $names = array_values(array_unique([...$sortable->names(), ...self::relationPaths($resource), ...AggregateSchema::keys($resource)]));
        $ranked = $root ? TextSearchType::of($resource)->sortNames($names) : $names;

        if ($ranked === []) {
            return null;
        }

        $sort = fn (): Type => Schemas::closed([
            'field' => (new StringType)->enum($ranked),
            'direction' => (new StringType)->enum(array_column(SortDirection::cases(), 'value')),
        ], ['field'], '`field` lists the default key of each aggregate that sorts, such as `comments_count`: an aggregate over a relation to many, on no field or on a field that is not a key. It sorts only when the same search declares that aggregate. The key of such an aggregate with an `alias` is accepted too, and is not listed.');

        return $ranked === $names ? $this->components->schema($this->records->name($resource).'Sort', $resource::class, $sort) : $sort();
    }

    /** @return list<string> */
    private static function relationPaths(Resource $resource): array
    {
        $paths = [];

        foreach ($resource->definition()->relations() as $relation) {
            if ($relation->hasMultipleEntries() || $relation instanceof MorphTo) {
                continue;
            }

            $fields = $relation
                ->resource()
                ->definition()
                ->fields();

            foreach (AggregateFunction::Max->acceptedFieldNames($fields) as $field) {
                $paths[] = $relation->relation.'.'.$field;
            }
        }

        return $paths;
    }
}

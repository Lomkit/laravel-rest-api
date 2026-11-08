<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;

final readonly class FilterSchema
{
    private RecordSchema $records;

    public function __construct(
        private ComponentRegistry $components,
    ) {
        $this->records = new RecordSchema($components);
    }

    public function list(Resource $resource): ?ArrayType
    {
        $filter = $this->reference($resource);

        if ($filter === null) {
            return null;
        }

        $list = Schemas::list($filter);
        $list->setMax($resource->context()->limits()->sizes->filters);

        return $list;
    }

    public function reference(Resource $resource): ?Reference
    {
        if (FilterPaths::of($resource) === [] && $resource->definition()->relations() === []) {
            return null;
        }

        $name = $this->records->name($resource);

        return $this->components->schema($name.'Filter', $resource::class, fn (): Type => $this->filter($resource, $name));
    }

    private function filter(Resource $resource, string $name): Type
    {
        $kinds = array_values(array_filter([$this->fields($resource, $name), $this->relations($resource, $name)]));
        $kinds[] = $this->components->schema($name.'FilterGroup', $resource::class, fn (): Type => $this->group($resource));

        return (new OneOf($kinds))->setDescription('A condition on a field, a condition on related rows, or a group of filters. `type` joins it to the previous filter: `and`, the default, or `or`.');
    }

    private function fields(Resource $resource, string $name): ?Reference
    {
        $paths = FilterPaths::of($resource);

        if ($paths === []) {
            return null;
        }

        return $this->components->schema($name.'FieldFilter', $resource::class, function () use ($resource, $name, $paths): Type {
            $schema = new FieldFilterSchema($resource->context()->limits());
            $branches = [];

            foreach ($paths as $path => $field) {
                $branches[] = [$path, $name.'Filter'.FilterPaths::component($path), fn (): Type => $schema->schema($path, $field)];
            }

            return $this->components->discriminated('field', $resource::class, $branches)
                ->setDescription('A condition on a field. Without `operator`, the condition is `=`. The fields of each related resource are listed one level deep, such as `stage.name`; deeper dotted paths are accepted too and are not listed.');
        });
    }

    private function relations(Resource $resource, string $name): ?Reference
    {
        $relations = $resource->definition()->relations();

        if ($relations === []) {
            return null;
        }

        return $this->components->schema($name.'RelationFilter', $resource::class, function () use ($resource, $name, $relations): Type {
            $branches = [];

            foreach ($relations as $relation) {
                $branches[] = [$relation->relation, $name.'RelationFilter'.Str::studly($relation->relation), fn (): Type => $this->related($relation)];
            }

            return $this->components->discriminated('relation', $resource::class, $branches)
                ->setDescription('The rows that have a related row matching `nested`, or that have none when `exists` is false.');
        });
    }

    private function related(Relation $relation): ObjectType
    {
        $properties = [
            'relation' => (new StringType)->const($relation->relation),
            'exists' => new BooleanType,
            'type' => FieldFilterSchema::joint(),
        ];
        $nested = $relation instanceof PolymorphicRelation && $relation->targets()->isPolymorphic() ? null : $this->list($relation->resource());

        if ($nested !== null) {
            $properties['nested'] = $nested;
        }

        if ($relation instanceof PolymorphicRelation) {
            $properties['types'] = $this->types($relation);
        }

        return Schemas::closed($properties, ['relation']);
    }

    private function types(Relation&PolymorphicRelation $relation): ObjectType
    {
        $types = [];

        foreach ($relation->targets() as $target) {
            $list = $this->list($relation->resourceOf($target));

            if ($list !== null) {
                $types[$target->type] = $list;
            }
        }

        return Schemas::closed($types, [], 'The filters of each type of related row, keyed by morph type');
    }

    private function group(Resource $resource): ObjectType
    {
        $depth = $resource->context()->limits()->depths->filters;

        return Schemas::closed(
            ['nested' => $this->list($resource) ?? new ArrayType, 'type' => FieldFilterSchema::joint()],
            ['nested'],
            "Filters that count as one condition. Groups, and the `nested` filters of related rows, nest at most {$depth} deep.",
        );
    }
}

<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;
use Lomkit\Rest\Search\AggregateKey;
use Lomkit\Rest\Search\SortableAggregate;

final readonly class AggregateSchema
{
    private RecordSchema $records;

    public function __construct(
        private ComponentRegistry $components,
    ) {
        $this->records = new RecordSchema($components);
    }

    public function reference(Resource $resource): ?Reference
    {
        $relations = self::relations($resource);

        if ($relations === []) {
            return null;
        }

        $name = $this->records->name($resource);

        return $this->components->schema($name.'Aggregate', $resource::class, function () use ($resource, $name, $relations): Type {
            $branches = [];

            foreach ($relations as $relation) {
                $branches[] = [$relation->relation, $name.'Aggregate'.Str::studly($relation->relation), fn (): Type => $this->aggregate($relation)];
            }

            return $this->components->discriminated('relation', $resource::class, $branches)
                ->setDescription('A value computed over the related rows of each row.');
        });
    }

    /** @return list<string> */
    public static function keys(Resource $resource): array
    {
        $keys = [];

        foreach (self::relations($resource) as $relation) {
            $fields = $relation
                ->resource()
                ->definition()
                ->fields();

            foreach (AggregateFunction::cases() as $function) {
                foreach ($function->needsField() ? $function->acceptedFieldNames($fields) : [null] as $field) {
                    if (SortableAggregate::accepts([$relation], $field)) {
                        $keys[] = AggregateKey::named($relation->relation, $function, $field, null)->value;
                    }
                }
            }
        }

        return $keys;
    }

    private function aggregate(Relation $relation): Type
    {
        $groups = [];
        $fields = $relation
            ->resource()
            ->definition()
            ->fields();

        foreach (AggregateFunction::cases() as $function) {
            $accepted = $function->needsField() ? $function->acceptedFieldNames($fields) : [];

            if ($function->needsField() && $accepted === []) {
                continue;
            }

            $groups[implode(',', $accepted)]['fields'] = $accepted;
            $groups[implode(',', $accepted)]['types'][] = $function->value;
        }

        $branches = array_values(array_map(fn (array $group): ObjectType => $this->branch($relation, $group['types'], $group['fields']), $groups));

        return count($branches) === 1 ? $branches[0] : new OneOf($branches);
    }

    /**
     * @param  list<string>  $types
     * @param  list<string>  $fields
     */
    private function branch(Relation $relation, array $types, array $fields): ObjectType
    {
        $properties = ['relation' => (new StringType)->const($relation->relation), 'type' => (new StringType)->enum($types)];

        if ($fields !== []) {
            $properties['field'] = (new StringType)->enum($fields);
        }

        $properties['alias'] = SearchSchema::alias('The key of the value in each row; by default the relation, the type and the field in snake case, such as `comments_max_rating`.');
        $filters = (new FilterSchema($this->components))->list($relation->resource());

        if ($filters !== null) {
            $properties['filters'] = $filters;
        }

        return Schemas::closed($properties, ['relation', 'type', ...($fields === [] ? [] : ['field'])]);
    }

    /** @return list<Relation> */
    private static function relations(Resource $resource): array
    {
        return array_values(array_filter($resource->definition()->relations(), fn (Relation $relation): bool => ! $relation instanceof PolymorphicRelation));
    }
}

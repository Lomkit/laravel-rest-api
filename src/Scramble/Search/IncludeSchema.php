<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\OwnerRelation;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;

final readonly class IncludeSchema
{
    private const array KEYS = ['filters', 'gates', 'scopes', 'sorts', 'selects', 'aggregates', 'includes', 'instructions', 'limit'];

    private const array OWNER_KEYS = ['filters', 'gates', 'scopes', 'selects', 'aggregates', 'includes', 'instructions'];

    private RecordSchema $records;

    public function __construct(
        private ComponentRegistry $components,
    ) {
        $this->records = new RecordSchema($components);
    }

    public function reference(Resource $resource): ?Reference
    {
        $relations = $resource->definition()->relations();

        if ($relations === []) {
            return null;
        }

        $name = $this->records->name($resource);

        return $this->components->schema($name.'Include', $resource::class, function () use ($resource, $name, $relations): Type {
            $branches = [];
            $depth = $resource->context()->limits()->depths->includes;

            foreach ($relations as $relation) {
                $branches[] = [$relation->relation, $name.'Include'.Str::studly($relation->relation), fn (): Type => $this->entry($relation)];
            }

            return $this->components->discriminated('relation', $resource::class, $branches)
                ->setDescription("Related rows to add to each row. Includes nest at most {$depth} deep: each `includes` inside an include is one more level.");
        });
    }

    private function entry(Relation $relation): ObjectType
    {
        $properties = [
            'relation' => (new StringType)->const($relation->relation),
            'alias' => SearchSchema::alias('The key of the related rows in each row, in place of the name of the relation. It lets a search include one relation several times, which needs an alias on all but one of them.'),
        ];

        if (! $relation instanceof PolymorphicRelation) {
            return Schemas::closed([...$properties, ...$this->search($relation->resource(), $relation instanceof OwnerRelation ? self::OWNER_KEYS : self::KEYS)], ['relation']);
        }

        $single = $relation->targets()->isPolymorphic() ? [] : $this->search($relation->resource(), self::OWNER_KEYS);

        return Schemas::closed([...$properties, ...$single, 'types' => $this->types($relation)], ['relation']);
    }

    private function types(Relation&PolymorphicRelation $relation): ObjectType
    {
        $types = [];

        foreach ($relation->targets() as $target) {
            $types[$target->type] = Schemas::closed($this->search($relation->resourceOf($target), self::OWNER_KEYS));
        }

        return Schemas::closed($types, [], 'The search of each type of related row, keyed by morph type');
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, Type>
     */
    private function search(Resource $resource, array $keys): array
    {
        return (new SearchSchema($this->components))->properties($resource, $keys, false);
    }
}

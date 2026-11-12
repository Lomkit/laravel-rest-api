<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\KeySchema;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;
use Lomkit\Rest\Validation\Mutate\RelationMutationParser;

final readonly class RelationEntrySchema
{
    private RecordSchema $records;

    private WritableSchema $writable;

    public function __construct(
        private ComponentRegistry $components,
    ) {
        $this->records = new RecordSchema($components);
        $this->writable = new WritableSchema($components);
    }

    public function relations(Resource $resource, ?MutationOperation $operation): ?Reference
    {
        $relations = array_values(array_filter($resource->definition()->relations(), fn (Relation $relation): bool => $relation->acceptedOperations() !== []));
        $constrained = $operation?->constrainedAs();

        if ($relations === []) {
            return null;
        }

        if ($constrained !== null && ! array_any($relations, fn (Relation $relation): bool => RelationMutationParser::constraint($relation, $constrained) !== null)) {
            $constrained = null;
        }

        $name = $this->records->name($resource).Str::studly($constrained->value ?? '').'Relations';

        return $this->components->schema($name, $resource::class, fn (): Type => $this->object($resource, $relations, $constrained));
    }

    /** @return list<Relation> */
    public static function required(Resource $resource, MutationOperation $operation): array
    {
        return array_values(array_filter(
            $resource->definition()->relations(),
            fn (Relation $relation): bool => $relation->acceptedOperations() !== [] && RelationMutationParser::constraint($relation, $operation) === 'required',
        ));
    }

    /** @param list<Relation> $relations */
    private function object(Resource $resource, array $relations, ?MutationOperation $operation): ObjectType
    {
        $properties = [];
        $required = [];

        foreach ($relations as $relation) {
            $constraint = $operation === null ? null : RelationMutationParser::constraint($relation, $operation);

            if ($constraint === 'prohibited') {
                continue;
            }

            if ($constraint === 'required') {
                $required[] = $relation->relation;
            }

            $properties[$relation->relation] = $this->value($resource, $relation);
        }

        $depth = $resource->context()->limits()->depths->mutations;

        return Schemas::closed($properties, $required, "The operations on the related rows, by relation. Relations nest at most {$depth} deep: each `relations` inside an entry is one more level.");
    }

    private function value(Resource $resource, Relation $relation): Type
    {
        $entry = $this->entry($resource, $relation);

        if (! $relation->hasMultipleEntries()) {
            return $entry;
        }

        $deletes = $relation->isDeletable() ? 'Each key of a `delete` entry counts as one entry.' : '';
        $entries = Schemas::list($entry, trim($deletes.' '.SyncSchema::children($relation)));
        $entries->setMax($resource->context()->limits()->sizes->relationEntries);

        return $entries;
    }

    private function entry(Resource $resource, Relation $relation): Reference
    {
        $name = $this->records->name($resource).'RelationMutation'.Str::studly($relation->relation);

        return $this->components->schema($name, $resource::class, function () use ($resource, $relation, $name): Type {
            if (! $relation instanceof PolymorphicRelation) {
                return $this->operations($relation, $relation->resource());
            }

            if (! $relation->targets()->isPolymorphic()) {
                $type = (new StringType)->enum($relation->targets()->types());

                return $this->operations($relation, $relation->resource(), $type->setDescription('The morph type of the related row'));
            }

            $branches = [];

            foreach ($relation->targets() as $target) {
                $related = $relation->resourceOf($target);
                $branches[] = [$target->type, $name.$this->records->name($related), fn (): Type => $this->operations($relation, $related, (new StringType)->const($target->type), true)];
            }

            return $this->components->discriminated('type', $resource::class, $branches);
        });
    }

    private function operations(Relation $relation, Resource $related, ?Type $type = null, bool $typeRequired = false): Type
    {
        $groups = [];

        foreach ($relation->acceptedOperations() as $operation) {
            $keys = $this->keys($relation, $related, $operation);
            $signature = (string) json_encode(array_map(fn (Type $key): mixed => $key->serializeAs31(), $keys));
            $groups[$signature] ??= [[], $keys];
            $groups[$signature][0][] = $operation->value;
        }

        $branches = [];

        foreach ($groups as [$operations, $keys]) {
            $properties = ['operation' => (new StringType)->enum($operations), ...($type === null ? [] : ['type' => $type]), ...$keys];
            $kept = $relation->syncRequiresWithoutDetaching() ? ['without_detaching'] : [];
            $required = array_values(array_intersect(['operation', ...($typeRequired ? ['type'] : []), 'key', ...$kept], array_keys($properties)));
            $branches[] = Schemas::closed($properties, $required);
        }

        return count($branches) === 1 ? $branches[0] : new OneOf($branches);
    }

    /** @return array<string, Type> */
    private function keys(Relation $relation, Resource $related, MutationOperation $operation): array
    {
        $writesRow = $relation->writesRelatedRows($operation);
        $write = $writesRow ? $operation->writeOperation() : null;
        $pivot = $relation instanceof PivotRelation ? $operation->pivotWriteOperation() : null;

        return array_filter([
            'key' => $operation->requiresKey() ? SyncSchema::keys($relation, $related, $operation) ?? $this->key($relation, $related) : null,
            'version' => MutationSchema::version($related, $operation),
            'attributes' => $write === null ? null : $this->writable->attributes($related, $write),
            'pivot' => $pivot === null ? null : $this->writable->pivot($relation, $pivot),
            'without_detaching' => SyncSchema::withoutDetaching($relation, $operation),
            'relations' => $writesRow && $operation->takesRelations() ? $this->relations($related, $operation) : null,
        ]);
    }

    private function key(Relation $relation, Resource $related): Type
    {
        $key = KeySchema::value($related);

        if (! $relation->hasMultipleEntries()) {
            return $key;
        }

        $keys = Schemas::list(KeySchema::value($related));
        $keys->setMax($related->context()->limits()->sizes->values);

        return (new OneOf([$key, $keys]))->setDescription('A key, or a list of keys: the operation applies to each of them');
    }
}

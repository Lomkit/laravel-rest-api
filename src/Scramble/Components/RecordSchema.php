<?php

namespace Lomkit\Rest\Scramble\Components;

use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Discriminator;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\DocumentationContext;
use Lomkit\Rest\Scramble\FieldSchema;
use Lomkit\Rest\Scramble\Mutate\WritableSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;

final readonly class RecordSchema
{
    public function __construct(
        private ComponentRegistry $components,
    ) {}

    public function reference(Resource $resource): Reference
    {
        $name = DocumentationContext::nameOf($resource);
        $qualified = str_replace('\\', '.', Str::beforeLast($resource::class, '\\')).'.'.$name;

        return $this->components->schema($name, $resource::class, fn (): Type => $this->record($resource), $qualified);
    }

    public function name(Resource $resource): string
    {
        return $this->reference($resource)->fullName ?? DocumentationContext::nameOf($resource);
    }

    public function gates(Resource $resource, bool $classLevel): ?ObjectType
    {
        if (! $resource->isGatingEnabled()) {
            return null;
        }

        $names = $resource->context()->config()->gateNames;
        $vocabulary = $resource->definition()->gates();
        $gates = new ObjectType;

        foreach ($vocabulary->names() as $name) {
            $gate = $vocabulary->find($name);

            if ($gate !== null && $gate->isClassLevel() === $classLevel) {
                $gates->addProperty($names[$gate->key()] ?? $gate->key(), $this->answer());
            }
        }

        return $gates->properties === [] ? null : $gates;
    }

    private function record(Resource $resource): ObjectType
    {
        $definition = $resource->definition();
        $record = new ObjectType;
        $record->setDescription('A row: the fields that the search selects, every selectable field when it selects none, then the gates and the relations that it asks for. Aggregates and include aliases add keys named by the search.');

        $types = WritableSchema::morphTypes($resource);

        foreach ($definition->fields()->selectable() as $field) {
            $type = FieldSchema::of($field);
            $record->addProperty($field->name, isset($types[$field->name]) ? $type->enum($types[$field->name]) : $type);
        }

        $gates = $this->gates($resource, false);

        if ($gates !== null) {
            $record->addProperty($resource->context()->config()->gatesKey, $gates->setDescription('Present when the search lists gates: the answer to each gate that it lists'));
        }

        foreach ($definition->relations() as $relation) {
            $record->addProperty(Str::snake($relation->relation), $this->relation($relation)->setDescription('Present when the search includes the relation'));
        }

        $more = self::more($definition->relations());

        if ($more !== null) {
            $record->addProperty($resource->context()->config()->moreKey, $more);
        }

        return $record;
    }

    /** @param array<int, Relation> $relations */
    private static function more(array $relations): ?ArrayType
    {
        $many = array_filter($relations, static fn (Relation $relation): bool => $relation->hasMultipleEntries());

        if ($many === []) {
            return null;
        }

        $keys = new AnyOf;
        $keys->setItems([
            (new StringType)->enum(array_values(array_map(static fn (Relation $relation): string => Str::snake($relation->relation), $many))),
            (new StringType)->setDescription('The alias of an include'),
        ]);

        return Schemas::list($keys, 'Present when the search includes a to-many relation: the includes of the row that have more rows than their limit, by relation or alias');
    }

    private function relation(Relation $relation): Type
    {
        if ($relation instanceof PolymorphicRelation) {
            return $this->morph($relation)->nullable(true);
        }

        $target = $this->reference($relation->resource());

        if (! $relation->hasMultipleEntries()) {
            return $target->nullable(true);
        }

        return Schemas::list($relation instanceof PivotRelation ? $this->pivoted($relation, $target) : $target);
    }

    private function pivoted(PivotRelation $relation, Reference $target): AllOf
    {
        $pivot = new ObjectType;

        foreach ($relation->getPivotFields()->selectable() as $field) {
            $pivot->addProperty($field->name, FieldSchema::of($field));
        }

        $row = new AllOf;
        $row->setItems([$target, Schemas::object(['pivot' => $pivot])]);

        return $row;
    }

    private function morph(Relation&PolymorphicRelation $relation): OneOf
    {
        $key = $relation
            ->parentResource()
            ->context()
            ->config()
            ->morphTypeKey;
        $discriminator = new Discriminator($key);
        $branches = [];

        foreach ($relation->targets() as $target) {
            $resource = $relation->resourceOf($target);
            $branch = $this->components->schema($this->name($resource).'WithType', $resource::class, fn (): Type => self::typed($this->reference($resource), $key, $target->type));

            $discriminator->addMapping($target->type, $branch->getUri());
            $branches[] = $branch;
        }

        return new OneOf($branches, $discriminator);
    }

    private static function typed(Reference $record, string $key, string $type): AllOf
    {
        $typed = new AllOf;
        $typed->setItems([$record, Schemas::required([$key => (new StringType)->const($type)])]);

        return $typed;
    }

    private function answer(): Reference
    {
        return $this->components->schema('RestGateAnswer', self::class, fn (): Type => Schemas::required([
            'allowed' => new BooleanType,
            'message' => (new StringType)->nullable(true),
        ]));
    }
}

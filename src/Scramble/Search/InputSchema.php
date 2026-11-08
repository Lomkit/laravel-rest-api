<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionSupport;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\FieldSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;
use Lomkit\Rest\Text\TextBackend;

final readonly class InputSchema
{
    private RecordSchema $records;

    public function __construct(
        private ComponentRegistry $components,
    ) {
        $this->records = new RecordSchema($components);
    }

    public function scope(Resource $resource): ?Reference
    {
        $scopes = $resource->definition()->scopes();

        if ($scopes === []) {
            return null;
        }

        $name = $this->records->name($resource);

        return $this->components->schema($name.'Scope', $resource::class, function () use ($resource, $name, $scopes): Type {
            $branches = array_map(fn (Scope $scope): array => [$scope->name, $name.'Scope'.Str::studly($scope->name), fn (): Type => self::parameters($scope)], $scopes);

            return $this->components->discriminated('name', $resource::class, $branches);
        });
    }

    public function instruction(Resource $resource): ?Reference
    {
        $instructions = InstructionSupport::among($resource->definition()->instructions(), TextBackend::Query);

        if ($instructions === []) {
            return null;
        }

        $name = $this->records->name($resource);

        return $this->components->schema($name.'Instruction', $resource::class, function () use ($resource, $name, $instructions): Type {
            $branches = [];

            foreach ($instructions as $instruction) {
                $fields = $resource->definition()->inputFields($instruction);
                $branches[] = [$instruction->uriKey(), $name.'Instruction'.Str::studly($instruction->uriKey()), fn (): Type => $this->fields($instruction, $fields, $resource)];
            }

            return $this->components->discriminated('name', $resource::class, $branches);
        });
    }

    private static function parameters(Scope $scope): ObjectType
    {
        $fields = $scope->getFields();
        $properties = ['name' => (new StringType)->const($scope->name)];

        if (count($fields) === 0) {
            return Schemas::closed($properties, ['name']);
        }

        $items = [];
        $given = 0;

        foreach ($fields as $field) {
            $items[] = FieldSchema::written($field);
            $given = $field->hasDefault() ? $given : count($items);
        }

        $parameters = new ArrayType;
        $parameters->setPrefixItems($items);
        $parameters->setMin($given);
        $parameters->setMax(count($items));
        $parameters->setDescription('In this order: '.implode(', ', $fields->names()));

        return Schemas::closed([...$properties, 'parameters' => $parameters], $given === 0 ? ['name'] : ['name', 'parameters']);
    }

    private function fields(Instruction $instruction, FieldSet $fields, Resource $resource): ObjectType
    {
        $properties = [
            'name' => (new StringType)->const($instruction->uriKey()),
            'alias' => SearchSchema::alias('The key of this entry among the instructions of the search, in place of the instruction name. It lets a search send one instruction several times.'),
        ];
        $entries = [];

        foreach ($fields as $field) {
            $entries[] = Schemas::closed(['name' => (new StringType)->const($field->name), 'value' => FieldSchema::written($field)], ['name']);
        }

        if ($entries !== []) {
            $list = Schemas::list(count($entries) === 1 ? $entries[0] : new OneOf($entries));
            $list->setMax($resource->context()->limits()->sizes->fields);
            $properties['fields'] = $list;
        }

        return Schemas::closed($properties, ['name']);
    }
}

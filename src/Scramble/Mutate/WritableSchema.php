<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\Details\RuleDescriptions;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Fields\WriteRules;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\FieldSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Validation\Mutate\ResourceRules;

final readonly class WritableSchema
{
    private RecordSchema $records;

    public function __construct(
        private ComponentRegistry $components,
    ) {
        $this->records = new RecordSchema($components);
    }

    public function attributes(Resource $resource, WriteOperation $operation): ?Reference
    {
        $fields = $resource
            ->definition()
            ->fields()
            ->writableOn($operation);

        if (count($fields) === 0) {
            return null;
        }

        $name = $this->records->name($resource).Str::studly($operation->value);
        $rules = (new ResourceRules)->for($resource, $operation);
        $description = $operation === WriteOperation::Create ? 'The fields that `create` writes' : 'The fields that `update`, `sync` and `toggle` write';

        return $this->components->schema($name, $resource::class, fn (): Type => self::object($fields, $rules->all(), self::morphTypes($resource), $description));
    }

    public function pivot(PivotRelation $relation, WriteOperation $operation): ?ObjectType
    {
        $fields = $relation
            ->getPivotFields()
            ->writableOn($operation);

        if (count($fields) === 0) {
            return null;
        }

        return self::object($fields, WriteRules::of($relation->getPivotFields(), $operation)->all(), [], 'The pivot fields that the operation writes');
    }

    /** @return array<string, list<string>> */
    public static function morphTypes(Resource $resource): array
    {
        $types = [];

        foreach ($resource->definition()->relations() as $relation) {
            if ($relation instanceof PolymorphicRelation) {
                $types[$relation->columns()->typeColumn] = $relation->targets()->types();
            }
        }

        return $types;
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @param  array<string, list<string>>  $morphTypes
     */
    private static function object(FieldSet $fields, array $rules, array $morphTypes, string $description): ObjectType
    {
        $properties = [];

        foreach ($fields as $field) {
            $type = FieldSchema::of($field);
            $described = (new RuleDescriptions($rules[$field->name] ?? []))->toArray();

            if (isset($morphTypes[$field->name])) {
                $type->enum($morphTypes[$field->name]);
            }

            if ($described !== []) {
                $type->setDescription('Rules: '.implode(', ', $described));
            }

            $properties[$field->name] = $type;
        }

        return Schemas::closed($properties, [], $description);
    }
}

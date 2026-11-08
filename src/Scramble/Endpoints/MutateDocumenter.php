<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\DocumentationContext;
use Lomkit\Rest\Scramble\RelationMutationType;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\WritableFieldsType;

final readonly class MutateDocumenter implements EndpointDocumenter
{
    #[\Override]
    public function document(Operation $operation, DocumentationContext $context): void
    {
        $operation->summary("Mutate {$context->name}");

        $entry = new ObjectType;
        $entry->addProperty('operation', (new StringType)->enum(['create', 'update']));
        $entry->addProperty('key', (new IntegerType)->setDescription('Required for update'));
        $entry->addProperty('attributes', self::attributes($context->resource));

        $relations = $context->resource->definition()->relations();

        if ($relations !== []) {
            $relationsType = new ObjectType;

            foreach ($relations as $relation) {
                $relationsType->addProperty($relation->relation, (new RelationMutationType($relation))->toType());
            }

            $entry->addProperty('relations', $relationsType);
        }

        Schemas::requestBody($operation, Schemas::object(['mutate' => Schemas::list($entry)]));
    }

    private static function attributes(Resource $resource): ObjectType
    {
        $create = WriteOperation::Create;
        $update = WriteOperation::Update;

        return WritableFieldsType::of(
            $resource->definition()->fields(),
            [$create->value => $create, $update->value => $update],
            [$create->value => $resource->createRules(), $update->value => $resource->updateRules()],
            $resource->rules(),
        )->toType();
    }
}

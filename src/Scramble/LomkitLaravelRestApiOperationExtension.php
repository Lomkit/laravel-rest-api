<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Relations\Relation;

class LomkitLaravelRestApiOperationExtension extends OperationExtension
{
    /**
     * Generate OpenAPI documentation for a REST endpoint operation.
     */
    #[\Override]
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $controller = $routeInfo->route->getController();

        if (! $controller instanceof Controller) {
            return;
        }

        $context = new RestContext(RestRequest::createFrom(request()), app());
        $resource = $context->resource($controller::resourceClass());
        $fakeRequest = $context->request;
        $fields = $resource->getFields($fakeRequest);
        $relations = $resource->getRelations($fakeRequest);
        $resourceName = str_replace('Resource', '', class_basename($resource));
        $action = $routeInfo->route->getActionMethod();

        $operation->setTags([$resourceName]);

        match ($action) {
            'search' => $this->documentSearch($operation, $fields, $relations, $resourceName),
            'mutate' => $this->documentMutate($operation, $this->attributes($resource, $fakeRequest), $relations, $resourceName),
            'details' => $operation->summary("Get {$resourceName} details"),
            'destroy' => $this->documentDestroy($operation, $resourceName),
            'restore' => $this->documentRestore($operation, $resourceName),
            default => null,
        };
    }

    /**
     * Document the search endpoint: filters, sorts, selects, includes, scopes,
     * text search, aggregates, instructions, and gates.
     *
     * @param  array<int, Relation>  $relations
     */
    private function documentSearch(
        Operation $operation,
        FieldSet $fields,
        array $relations,
        string $resourceName
    ): void {
        $operation->summary("Search {$resourceName}");

        $fieldEnum = $fields->filterable()->names();
        $relationEnum = array_values(array_map(fn (Relation $relation): string => $relation->relation, $relations));
        $relationDesc = $this->buildRelationsDescription($relations);

        $body = (new ObjectType)->addProperty(
            'search',
            (new ObjectType)
                ->addProperty('filters', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty('field', (new StringType)->enum($fieldEnum))
                        ->addProperty('operator', (new StringType)->enum(array_column(FilterOperator::cases(), 'value')))
                        ->addProperty('value', (new MixedType)->setDescription('Accepts string, integer, boolean, or array'))
                        ->addProperty('type', (new StringType)->enum(['and', 'or']))
                        ->addProperty('nested', new ArrayType)
                ))
                ->addProperty('sorts', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty('field', (new StringType)->enum($fields->sortable()->names()))
                        ->addProperty('direction', (new StringType)->enum(array_column(SortDirection::cases(), 'value')))
                ))
                ->addProperty('selects', (new ArrayType)->setItems(
                    (new ObjectType)->addProperty('field', (new StringType)->enum($fields->selectable()->names()))
                ))
                ->addProperty('includes', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty(
                            'relation',
                            empty($relationEnum)
                                ? new StringType
                                : (new StringType)->enum($relationEnum)->setDescription($relationDesc)
                        )
                        ->addProperty(
                            'alias',
                            (new StringType)->setDescription('Optional output key for this include, used verbatim. Allows including the same relation several times under different keys. Must match ^[A-Za-z_][A-Za-z0-9_]*$, be unique across the includes, and not collide with a field, a relation or the gates key. Not allowed on a dotted relation path.')
                        )
                        ->addProperty('limit', new IntegerType)
                        ->addProperty('filters', new ArrayType)
                        ->addProperty('sorts', new ArrayType)
                ))
                ->addProperty('scopes', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty('name', new StringType)
                        ->addProperty('parameters', new ArrayType)
                ))
                ->addProperty(
                    'text',
                    (new ObjectType)
                        ->addProperty('value', new StringType)
                        ->addProperty('fields', (new ArrayType)->setItems(new StringType))
                        ->setDescription('Full-text search across specified fields')
                )
                ->addProperty('aggregates', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty('relation', new StringType)
                        ->addProperty('type', (new StringType)->enum(array_column(AggregateFunction::cases(), 'value')))
                        ->addProperty('field', new StringType)
                        ->addProperty('filters', new ArrayType)
                )->setDescription('Aggregate values over relations'))
                ->addProperty('instructions', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty('name', new StringType)
                        ->addProperty('fields', new ArrayType)
                )->setDescription('Named query instructions defined on the resource'))
                ->addProperty('gates', (new ArrayType)->setItems(new StringType)
                    ->setDescription('Gates to evaluate for the current user on each result'))
                ->addProperty('page', new IntegerType)
                ->addProperty('limit', new IntegerType)
        );

        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent('application/json', Schema::fromType($body))
        );
    }

    private function attributes(Resource $resource, RestRequest $request): ObjectType
    {
        $create = WriteOperation::Create;
        $update = WriteOperation::Update;

        return WritableFieldsType::of(
            $resource->getFields($request),
            [$create->value => $create, $update->value => $update],
            [$create->value => $resource->createRules($request), $update->value => $resource->updateRules($request)],
            $resource->rules($request),
        )->toType();
    }

    /** @param array<int, Relation> $relations */
    private function documentMutate(Operation $operation, ObjectType $attributes, array $relations, string $resourceName): void
    {
        $operation->summary("Mutate {$resourceName}");

        $entry = new ObjectType;
        $entry->addProperty('operation', (new StringType)->enum(['create', 'update']));
        $entry->addProperty('key', (new IntegerType)->setDescription('Required for update'));
        $entry->addProperty('attributes', $attributes);

        if ($relations !== []) {
            $relationsType = new ObjectType;

            foreach ($relations as $relation) {
                $relationsType->addProperty($relation->relation, (new RelationMutationType($relation))->toType());
            }

            $entry->addProperty('relations', $relationsType);
        }

        $entries = new ArrayType;
        $entries->setItems($entry);

        $body = new ObjectType;
        $body->addProperty('mutate', $entries);

        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent('application/json', Schema::fromType($body))
        );
    }

    /**
     * Document the destroy endpoint.
     */
    private function documentDestroy(Operation $operation, string $resourceName): void
    {
        $operation->summary("Delete {$resourceName}");

        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent('application/json', Schema::fromType(
                (new ObjectType)->addProperty('resources', (new ArrayType)->setItems(new IntegerType))
            ))
        );
    }

    /**
     * Document the restore endpoint.
     */
    private function documentRestore(Operation $operation, string $resourceName): void
    {
        $operation->summary("Restore {$resourceName}");

        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent('application/json', Schema::fromType(
                (new ObjectType)->addProperty('resources', (new ArrayType)->setItems(new IntegerType))
            ))
        );
    }

    /**
     * Build a markdown description listing all available relations with their types.
     *
     * @param  array<int, Relation>  $relations
     */
    private function buildRelationsDescription(array $relations): string
    {
        if (empty($relations)) {
            return '';
        }

        $lines = ["**Available relations:**\n"];
        foreach ($relations as $relation) {
            $lines[] = '- `'.$relation->relation.'` ('.class_basename($relation).')';
        }

        return implode("\n", $lines);
    }
}

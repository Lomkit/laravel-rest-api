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
use Dedoc\Scramble\Support\Generator\Types\Type;
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
        $fields = $resource->definition()->fields();
        $relations = $resource->definition()->relations();
        $resourceName = str_replace('Resource', '', class_basename($resource));
        $action = $routeInfo->route->getActionMethod();

        $operation->setTags([$resourceName]);

        match ($action) {
            'search' => $this->documentSearch($operation, $resource, $fields, $relations, $resourceName),
            'mutate' => $this->documentMutate($operation, $this->attributes($resource), $relations, $resourceName),
            'details' => DetailsResponseType::document($operation, $resourceName),
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
        Resource $resource,
        FieldSet $fields,
        array $relations,
        string $resourceName
    ): void {
        $operation->summary("Search {$resourceName}");

        $fieldEnum = $fields->filterable()->names();
        $relationEnum = array_values(array_map(fn (Relation $relation): string => $relation->relation, $relations));
        $relationDesc = $this->buildRelationsDescription($relations);
        $text = TextSearchType::of($resource);

        $include = new ObjectType;
        $include->addProperty('relation', empty($relationEnum) ? new StringType : (new StringType)->enum($relationEnum)->setDescription($relationDesc));
        $include->addProperty('alias', (new StringType)->setDescription('Optional output key for this include, used verbatim. Allows including the same relation several times under different keys. Must match ^[A-Za-z_][A-Za-z0-9_]*$, be unique across the includes, and not collide with a field, a relation, the key of an aggregate or the gates key. Not allowed on a dotted relation path.'));
        $include->addProperty('limit', new IntegerType);
        $include->addProperty('filters', new ArrayType);
        $include->addProperty('sorts', new ArrayType);
        $include->addProperty('types', (new ObjectType)->setDescription('MorphTo relations only: the search of each type, keyed by morph type, with selects, filters, scopes, includes, aggregates, instructions and gates'));

        $search = $this->objectOf([
            'filters' => $this->listOf($this->objectOf([
                'field' => (new StringType)->enum($fieldEnum),
                'operator' => (new StringType)->enum(array_column(FilterOperator::cases(), 'value')),
                'value' => (new MixedType)->setDescription('Accepts string, integer, boolean, or array'),
                'type' => (new StringType)->enum(['and', 'or']),
                'nested' => new ArrayType,
            ])),
            'sorts' => $this->listOf($this->objectOf([
                'field' => (new StringType)->enum($text->sortNames($fields->sortable()->names())),
                'direction' => (new StringType)->enum(array_column(SortDirection::cases(), 'value')),
            ])),
            'selects' => $this->listOf($this->objectOf(['field' => (new StringType)->enum($fields->selectable()->names())])),
            'includes' => $this->listOf($include),
            'scopes' => $this->listOf($this->objectOf(['name' => new StringType, 'parameters' => new ArrayType])),
        ]);

        $text->addTo($search);

        $aggregate = $this->objectOf([
            'relation' => new StringType,
            'type' => (new StringType)->enum(array_column(AggregateFunction::cases(), 'value')),
            'field' => new StringType,
            'filters' => new ArrayType,
        ]);
        $instruction = $this->objectOf(['name' => new StringType, 'fields' => new ArrayType]);

        $search->addProperty('aggregates', $this->listOf($aggregate)->setDescription('Aggregate values over relations'));
        $search->addProperty('instructions', $this->listOf($instruction)->setDescription('Named query instructions defined on the resource'));
        $search->addProperty('gates', $this->listOf(new StringType)->setDescription('Gates to evaluate for the current user on each result'));
        $search->addProperty('page', new IntegerType);
        $search->addProperty('limit', new IntegerType);

        $this->requestBody($operation, $this->objectOf(['search' => $search]));
    }

    private function attributes(Resource $resource): ObjectType
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

        $this->requestBody($operation, $this->objectOf(['mutate' => $this->listOf($entry)]));
    }

    /**
     * Document the destroy endpoint.
     */
    private function documentDestroy(Operation $operation, string $resourceName): void
    {
        $operation->summary("Delete {$resourceName}");

        $this->requestBody($operation, $this->objectOf(['resources' => $this->listOf(new IntegerType)]));
    }

    /**
     * Document the restore endpoint.
     */
    private function documentRestore(Operation $operation, string $resourceName): void
    {
        $operation->summary("Restore {$resourceName}");

        $this->requestBody($operation, $this->objectOf(['resources' => $this->listOf(new IntegerType)]));
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

    /** @param array<string, Type> $properties */
    private function objectOf(array $properties): ObjectType
    {
        $object = new ObjectType;

        foreach ($properties as $name => $property) {
            $object->addProperty($name, $property);
        }

        return $object;
    }

    private function listOf(Type $items): ArrayType
    {
        $list = new ArrayType;
        $list->setItems($items);

        return $list;
    }

    private function requestBody(Operation $operation, ObjectType $body): void
    {
        $schema = new Schema;
        $schema->type = $body;

        $operation->addRequestBodyObject(RequestBodyObject::make()->setContent('application/json', $schema));
    }
}

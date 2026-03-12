<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;

class LomkitOperationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $controller = $routeInfo->route->getController();

        if (!$controller instanceof Controller) {
            return;
        }

        $resourceClass = $controller::$resource;
        $resource = new $resourceClass;
        $fakeRequest = app(RestRequest::class);
        $fields = $resource->fields($fakeRequest);
        $relations = $this->parseRelationsFromSource($resource);
        $rules = $this->extractAllRules($resource, $fakeRequest);
        $resourceName = str_replace('Resource', '', class_basename($resource));
        $action = $routeInfo->route->getActionMethod();

        $operation->setTags([$resourceName]);

        match ($action) {
            'search' => $this->documentSearch($operation, $fields, $relations, $resourceName),
            'mutate' => $this->documentMutate($operation, $fields, $relations, $rules, $resourceName),
            'details' => $operation->summary("Get {$resourceName} details"),
            'destroy' => $this->documentDestroy($operation, $resourceName),
            'restore' => $this->documentRestore($operation, $resourceName),
            default => null,
        };
    }

    private function parseRelationsFromSource($resource): array
    {
        $rc = new \ReflectionClass($resource);
        $method = $rc->getMethod('relations');
        $lines = file($method->getFileName());
        $source = implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        preg_match_all(
            '/(\w+)::make\s*\(\s*[\'"]([^\'"]+)[\'"]/',
            $source,
            $matches,
            PREG_SET_ORDER
        );

        return array_map(fn($m) => [
            'name' => $m[2],
            'type' => $m[1],
        ], $matches);
    }

    private function extractAllRules($resource, RestRequest $request): array
    {
        $all = method_exists($resource, 'rules') ? $resource->rules($request) : [];
        $create = method_exists($resource, 'createRules') ? $resource->createRules($request) : [];
        $update = method_exists($resource, 'updateRules') ? $resource->updateRules($request) : [];

        $fields = array_unique(array_merge(
            array_keys($all),
            array_keys($create),
            array_keys($update)
        ));

        $result = [];
        foreach ($fields as $field) {
            $result[$field] = [
                'all' => (array)($all[$field] ?? []),
                'create' => (array)($create[$field] ?? []),
                'update' => (array)($update[$field] ?? []),
            ];
        }

        return $result;
    }

    private function documentSearch(
        Operation $operation,
        array     $fields,
        array     $relations,
        string    $resourceName
    ): void
    {
        $operation->summary("Search {$resourceName}");

        $fieldEnum = array_values($fields);
        $relationEnum = array_column($relations, 'name');
        $relationDesc = $this->buildRelationsDescription($relations);

        $body = (new ObjectType)->addProperty(
            'search',
            (new ObjectType)
                ->addProperty('filters', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty('field', (new StringType)->enum($fieldEnum))
                        ->addProperty('operator', (new StringType)->enum(['=', '!=', '>', '>=', '<', '<=', 'in', 'not in', 'like']))
                        ->addProperty('value', new StringType)
                        ->addProperty('type', (new StringType)->enum(['and', 'or']))
                        ->addProperty('nested', new ArrayType)
                ))
                ->addProperty('sorts', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty('field', (new StringType)->enum($fieldEnum))
                        ->addProperty('direction', (new StringType)->enum(['asc', 'desc']))
                ))
                ->addProperty('selects', (new ArrayType)->setItems(
                    (new ObjectType)->addProperty('field', (new StringType)->enum($fieldEnum))
                ))
                ->addProperty('includes', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty(
                            'relation',
                            empty($relationEnum)
                                ? new StringType
                                : (new StringType)->enum($relationEnum)->setDescription($relationDesc)
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
                ->addProperty('page', new IntegerType)
                ->addProperty('limit', new IntegerType)
        );

        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent('application/json', Schema::fromType($body))
        );
    }

    private function documentMutate(
        Operation $operation,
        array     $fields,
        array     $relations,
        array     $rules,
        string    $resourceName
    ): void
    {
        $operation->summary("Mutate {$resourceName}");

        $attributesType = new ObjectType;
        foreach ($fields as $field) {
            $fieldRules = $rules[$field] ?? ['all' => [], 'create' => [], 'update' => []];
            $allRules = array_merge($fieldRules['all'], $fieldRules['create']);
            $type = $this->resolveFieldType($allRules);
            $description = $this->buildRuleDescription($fieldRules);

            if ($description) {
                $type->setDescription($description);
            }

            $attributesType->addProperty($field, $type);
        }

        $recordType = (new ObjectType)
            ->addProperty('operation', (new StringType)->enum(['create', 'update']))
            ->addProperty('key', (new IntegerType)->setDescription('Required for update'))
            ->addProperty('attributes', $attributesType);

        if (!empty($relations)) {
            $relationsType = new ObjectType;
            foreach ($relations as $relation) {
                $relationsType->addProperty(
                    $relation['name'],
                    $this->buildRelationMutationType($relation['type'])
                        ->setDescription("Type: {$relation['type']}")
                );
            }
            $recordType->addProperty('relations', $relationsType);
        }

        $body = (new ObjectType)->addProperty(
            'mutate',
            (new ArrayType)->setItems($recordType)
        );

        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent('application/json', Schema::fromType($body))
        );
    }

    private function documentDestroy(Operation $operation, string $resourceName): void
    {
        $operation->summary("Delete {$resourceName}");

        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent('application/json', Schema::fromType(
                (new ObjectType)->addProperty('resources', (new ArrayType)->setItems(new IntegerType))
            ))
        );
    }

    private function documentRestore(Operation $operation, string $resourceName): void
    {
        $operation->summary("Restore {$resourceName}");

        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent('application/json', Schema::fromType(
                (new ObjectType)->addProperty('resources', (new ArrayType)->setItems(new IntegerType))
            ))
        );
    }

    private function resolveFieldType(array $rules): Type
    {
        $flat = array_map(fn($rule) => is_string($rule) ? strtolower($rule) : '', $rules);

        if (array_intersect(['integer', 'numeric', 'int'], $flat)) return new IntegerType;
        if (array_intersect(['boolean', 'bool'], $flat)) return new BooleanType;
        if (array_intersect(['array'], $flat)) return new ArrayType;

        return new StringType;
    }

    private function buildRuleDescription(array $fieldRules): string
    {
        $parts = [];
        foreach (['all', 'create', 'update'] as $context) {
            $r = array_filter($fieldRules[$context], fn($v) => is_string($v));
            if (!empty($r)) {
                $parts[] = "{$context}: " . implode(', ', $r);
            }
        }
        return implode(' | ', $parts);
    }

    private function buildRelationsDescription(array $relations): string
    {
        if (empty($relations)) return '';

        $lines = ["**Available relations:**\n"];
        foreach ($relations as $rel) {
            $lines[] = "- `{$rel['name']}` ({$rel['type']})";
        }
        return implode("\n", $lines);
    }

    private function buildRelationMutationType(string $relationType): Type
    {
        $single = ['BelongsTo', 'HasOne', 'MorphOne', 'MorphTo', 'HasOneThrough', 'HasOneOfMany', 'MorphOneOfMany'];
        $many = ['HasMany', 'BelongsToMany', 'MorphMany', 'MorphToMany', 'MorphedByMany', 'HasManyThrough'];

        $recordType = (new ObjectType)
            ->addProperty('operation', (new StringType)->enum(['create', 'update', 'attach', 'detach', 'sync', 'toggle']))
            ->addProperty('key', (new IntegerType)->setDescription('Required for update/attach/detach'))
            ->addProperty('attributes', (new ObjectType)->setDescription('Fields of the related resource'));

        if (in_array($relationType, $single)) {
            return $recordType;
        }

        if (in_array($relationType, $many)) {
            return (new ArrayType)->setItems($recordType);
        }

        return $recordType;
    }
}

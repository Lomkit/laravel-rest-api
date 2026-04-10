<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Validation\Rules\In;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;

class LomkitLaravelRestApiOperationExtension extends OperationExtension
{
    /**
     * Generate OpenAPI documentation for a REST endpoint operation.
     */
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $controller = $routeInfo->route->getController();

        if (! $controller instanceof Controller) {
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

    /**
     * Parse relation definitions from the resource's source code via reflection.
     *
     * Falls back to an empty array if the relations() method cannot be read.
     *
     * @return array<int, array{name: string, type: string}>
     */
    private function parseRelationsFromSource(object $resource): array
    {
        try {
            $rc = new \ReflectionClass($resource);

            if (! $rc->hasMethod('relations')) {
                return [];
            }

            $method = $rc->getMethod('relations');
            $fileName = $method->getFileName();

            if ($fileName === false) {
                return [];
            }

            $lines = file($fileName);

            if ($lines === false) {
                return [];
            }

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

            return array_map(fn ($m) => [
                'name' => $m[2],
                'type' => $m[1],
            ], $matches);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Collect validation rules from rules(), createRules(), and updateRules()
     * and index them by field name with per-context buckets.
     *
     * @return array<string, array{all: array, create: array, update: array}>
     */
    private function extractAllRules(object $resource, RestRequest $request): array
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
                'all' => (array) ($all[$field] ?? []),
                'create' => (array) ($create[$field] ?? []),
                'update' => (array) ($update[$field] ?? []),
            ];
        }

        return $result;
    }

    /**
     * Document the search endpoint: filters, sorts, selects, includes, scopes,
     * text search, aggregates, instructions, and gates.
     */
    private function documentSearch(
        Operation $operation,
        array $fields,
        array $relations,
        string $resourceName
    ): void {
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
                        ->addProperty('operator', (new StringType)->enum([
                            '=', '!=', '>', '>=', '<', '<=',
                            'in', 'not in', 'like', 'not like',
                        ]))
                        ->addProperty('value', (new MixedType)->setDescription('Accepts string, integer, boolean, or array'))
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
                ->addProperty('text', (new ObjectType)
                    ->addProperty('value', new StringType)
                    ->addProperty('fields', (new ArrayType)->setItems(new StringType))
                    ->setDescription('Full-text search across specified fields')
                )
                ->addProperty('aggregates', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty('relation', new StringType)
                        ->addProperty('type', (new StringType)->enum(['count', 'min', 'max', 'avg', 'sum', 'exists']))
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

    /**
     * Document the mutate endpoint: create and update operations with attributes
     * and nested relation mutations.
     */
    private function documentMutate(
        Operation $operation,
        array $fields,
        array $relations,
        array $rules,
        string $resourceName
    ): void {
        $operation->summary("Mutate {$resourceName}");

        $attributesType = new ObjectType;
        foreach ($fields as $field) {
            $fieldRules = $rules[$field] ?? ['all' => [], 'create' => [], 'update' => []];
            // Include all three contexts so update-only rules are also reflected.
            $allRules = array_merge($fieldRules['all'], $fieldRules['create'], $fieldRules['update']);
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

        if (! empty($relations)) {
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
     * Infer an OpenAPI type from a flat list of Laravel validation rules.
     *
     * Handles both string rules (e.g. 'integer', 'boolean') and object rules
     * such as Rule::in(), which indicate a string enum.
     */
    private function resolveFieldType(array $rules): Type
    {
        $flat = [];
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                // Split on '|' (defensive against un-normalized input) and strip
                // any parameter suffix after ':' (e.g. 'decimal:2' → 'decimal',
                // 'array:name,username' → 'array').
                foreach (explode('|', $rule) as $token) {
                    $flat[] = strtolower(trim(explode(':', $token)[0]));
                }
            } elseif ($rule instanceof In) {
                // Rule::in(...) constrains values but the underlying type is string.
                $flat[] = 'string';
            }
            // Other Rule objects (Unique, Exists, etc.) do not affect the type.
        }

        if (array_intersect(['integer', 'int'], $flat)) {
            return new IntegerType;
        }

        if (array_intersect(['numeric', 'decimal'], $flat)) {
            return new NumberType;
        }

        if (array_intersect(['boolean', 'bool'], $flat)) {
            return new BooleanType;
        }

        if (array_intersect(['array'], $flat)) {
            return new ArrayType;
        }

        return new StringType;
    }

    /**
     * Build a human-readable rule description string grouped by context.
     */
    private function buildRuleDescription(array $fieldRules): string
    {
        $parts = [];
        foreach (['all', 'create', 'update'] as $context) {
            $r = array_filter($fieldRules[$context], fn ($v) => is_string($v));
            if (! empty($r)) {
                $parts[] = "{$context}: ".implode(', ', $r);
            }
        }

        return implode(' | ', $parts);
    }

    /**
     * Build a markdown description listing all available relations with their types.
     */
    private function buildRelationsDescription(array $relations): string
    {
        if (empty($relations)) {
            return '';
        }

        $lines = ["**Available relations:**\n"];
        foreach ($relations as $rel) {
            $lines[] = "- `{$rel['name']}` ({$rel['type']})";
        }

        return implode("\n", $lines);
    }

    /**
     * Build the OpenAPI type for a relation mutation payload.
     *
     * Single-record relations return an ObjectType; collection relations return
     * an ArrayType of that object. Each record exposes:
     *   - operation  (create | update | attach | detach | sync | toggle)
     *   - key        (integer OR array of integers for bulk operations)
     *   - attributes (fields of the related resource)
     *   - pivot      (optional pivot-table attributes, many-to-many only)
     *   - without_detaching (optional, many-to-many only)
     */
    private function buildRelationMutationType(string $relationType): Type
    {
        $single = ['BelongsTo', 'HasOne', 'MorphOne', 'MorphTo', 'HasOneThrough', 'HasOneOfMany', 'MorphOneOfMany'];
        $many = ['HasMany', 'BelongsToMany', 'MorphMany', 'MorphToMany', 'MorphedByMany', 'HasManyThrough'];
        $pivotMany = ['BelongsToMany', 'MorphToMany', 'MorphedByMany'];

        $recordType = (new ObjectType)
            ->addProperty('operation', (new StringType)->enum(['create', 'update', 'attach', 'detach', 'sync', 'toggle']))
            ->addProperty('key', (new MixedType)->setDescription('Integer for single operations; array of integers for bulk operations'))
            ->addProperty('attributes', (new ObjectType)->setDescription('Fields of the related resource'));

        if (in_array($relationType, $many)) {
            if (in_array($relationType, $pivotMany)) {
                $recordType
                    ->addProperty('pivot', (new ObjectType)->setDescription('Pivot-table attributes'))
                    ->addProperty('without_detaching', (new BooleanType)->setDescription('When true, existing relations are kept during sync'));
            }

            return (new ArrayType)->setItems($recordType);
        }

        return $recordType;
    }
}

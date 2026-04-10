<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Illuminate\Validation\Rules\In;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Scramble\LomkitLaravelRestApiOperationExtension;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\TestCase;
use Mockery;

class LomkitLaravelRestApiOperationExtensionTest extends TestCase
{
    private LomkitLaravelRestApiOperationExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(OperationExtension::class)) {
            $this->markTestSkipped('dedoc/scramble is not installed.');
        }

        $this->extension = new LomkitLaravelRestApiOperationExtension(
            Mockery::mock(Infer::class),
            Mockery::mock(TypeTransformer::class),
            new GeneratorConfig,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function callPrivate(string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod(LomkitLaravelRestApiOperationExtension::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($this->extension, $args);
    }

    // -------------------------------------------------------------------------
    // resolveFieldType
    // -------------------------------------------------------------------------

    public function test_resolves_integer_type_from_integer_rule(): void
    {
        $this->assertInstanceOf(IntegerType::class, $this->callPrivate('resolveFieldType', [['integer']]));
    }

    public function test_resolves_number_type_from_numeric_rule(): void
    {
        // 'numeric' allows floats, so it maps to NumberType, not IntegerType.
        $this->assertInstanceOf(NumberType::class, $this->callPrivate('resolveFieldType', [['numeric']]));
    }

    public function test_resolves_boolean_type_from_boolean_rule(): void
    {
        $this->assertInstanceOf(BooleanType::class, $this->callPrivate('resolveFieldType', [['boolean']]));
    }

    public function test_resolves_boolean_type_from_bool_alias(): void
    {
        $this->assertInstanceOf(BooleanType::class, $this->callPrivate('resolveFieldType', [['bool']]));
    }

    public function test_resolves_array_type_from_array_rule(): void
    {
        $this->assertInstanceOf(ArrayType::class, $this->callPrivate('resolveFieldType', [['array']]));
    }

    public function test_resolves_string_type_as_default(): void
    {
        $this->assertInstanceOf(StringType::class, $this->callPrivate('resolveFieldType', [[]]));
        $this->assertInstanceOf(StringType::class, $this->callPrivate('resolveFieldType', [['string']]));
    }

    public function test_resolves_string_type_for_rule_in_object(): void
    {
        // Rule::in() constrains values but the field is still a string type.
        $result = $this->callPrivate('resolveFieldType', [[new In(['a', 'b', 'c'])]]);
        $this->assertInstanceOf(StringType::class, $result);
    }

    public function test_rule_object_mixed_with_string_rules_does_not_break_type_resolution(): void
    {
        // A Rule object alongside 'integer' must not erase the integer inference.
        $result = $this->callPrivate('resolveFieldType', [['integer', new In([1, 2, 3])]]);
        $this->assertInstanceOf(IntegerType::class, $result);
    }

    public function test_rule_string_comparison_is_case_insensitive(): void
    {
        $this->assertInstanceOf(IntegerType::class, $this->callPrivate('resolveFieldType', [['Integer']]));
        $this->assertInstanceOf(BooleanType::class, $this->callPrivate('resolveFieldType', [['Boolean']]));
    }

    // -------------------------------------------------------------------------
    // extractAllRules – update rules included
    // -------------------------------------------------------------------------

    public function test_extract_all_rules_merges_all_three_contexts(): void
    {
        $resource = new class
        {
            public function rules(RestRequest $request): array
            {
                return ['shared' => ['string']];
            }

            public function createRules(RestRequest $request): array
            {
                return ['create_only' => ['string']];
            }

            public function updateRules(RestRequest $request): array
            {
                return ['update_only' => ['integer']];
            }
        };

        $request = app(RestRequest::class);
        $result = $this->callPrivate('extractAllRules', [$resource, $request]);

        $this->assertArrayHasKey('shared', $result);
        $this->assertArrayHasKey('create_only', $result);
        $this->assertArrayHasKey('update_only', $result);
    }

    public function test_extract_all_rules_update_only_field_resolves_correct_type(): void
    {
        // Before the fix, update-only rules were ignored and the field fell back
        // to StringType. Verify that the rules are preserved in the 'update' bucket.
        $resource = new class
        {
            public function rules(RestRequest $request): array
            {
                return [];
            }

            public function createRules(RestRequest $request): array
            {
                return [];
            }

            public function updateRules(RestRequest $request): array
            {
                return ['age' => ['integer']];
            }
        };

        $request = app(RestRequest::class);
        $result = $this->callPrivate('extractAllRules', [$resource, $request]);

        $this->assertSame(['integer'], $result['age']['update']);

        // Confirm resolveFieldType uses those update rules correctly.
        $allRules = array_merge($result['age']['all'], $result['age']['create'], $result['age']['update']);
        $this->assertInstanceOf(IntegerType::class, $this->callPrivate('resolveFieldType', [$allRules]));
    }

    public function test_extract_all_rules_gracefully_handles_missing_rule_methods(): void
    {
        $resource = new class {};  // no rules() / createRules() / updateRules()

        $request = app(RestRequest::class);
        $result = $this->callPrivate('extractAllRules', [$resource, $request]);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // parseRelationsFromSource
    // -------------------------------------------------------------------------

    public function test_parse_relations_returns_empty_array_when_no_relations_method(): void
    {
        $resource = new class {};

        $result = $this->callPrivate('parseRelationsFromSource', [$resource]);

        $this->assertSame([], $result);
    }

    public function test_parse_relations_returns_empty_array_on_reflection_error(): void
    {
        // A stdClass instance has no relations() method, so ReflectionClass::getMethod()
        // throws a ReflectionException, exercising the catch (\Throwable) branch.
        $result = $this->callPrivate('parseRelationsFromSource', [new \stdClass]);

        $this->assertSame([], $result);
    }

    public function test_parse_relations_from_real_resource(): void
    {
        // ModelResource defines relations using Type::make('name', …) which is
        // exactly the pattern the regex targets.
        $resource = new ModelResource;
        $result = $this->callPrivate('parseRelationsFromSource', [$resource]);

        $this->assertNotEmpty($result);

        $names = array_column($result, 'name');
        $this->assertContains('hasOneRelation', $names);
        $this->assertContains('hasManyRelation', $names);
        $this->assertContains('belongsToManyRelation', $names);

        foreach ($result as $relation) {
            $this->assertArrayHasKey('name', $relation);
            $this->assertArrayHasKey('type', $relation);
        }
    }

    // -------------------------------------------------------------------------
    // buildRelationMutationType
    // -------------------------------------------------------------------------

    public function test_single_relation_returns_object_type(): void
    {
        foreach (['BelongsTo', 'HasOne', 'MorphOne', 'MorphTo', 'HasOneThrough', 'HasOneOfMany', 'MorphOneOfMany'] as $type) {
            $result = $this->callPrivate('buildRelationMutationType', [$type]);
            $this->assertInstanceOf(ObjectType::class, $result, "Expected ObjectType for {$type}");
        }
    }

    public function test_many_relation_returns_array_type(): void
    {
        foreach (['HasMany', 'BelongsToMany', 'MorphMany', 'MorphToMany', 'MorphedByMany', 'HasManyThrough'] as $type) {
            $result = $this->callPrivate('buildRelationMutationType', [$type]);
            $this->assertInstanceOf(ArrayType::class, $result, "Expected ArrayType for {$type}");
        }
    }

    public function test_pivot_many_relation_record_has_pivot_and_without_detaching(): void
    {
        foreach (['BelongsToMany', 'MorphToMany', 'MorphedByMany'] as $type) {
            $result = $this->callPrivate('buildRelationMutationType', [$type]);
            $items = $result->items;
            $this->assertArrayHasKey('pivot', $items->properties, "{$type} should have pivot");
            $this->assertArrayHasKey('without_detaching', $items->properties, "{$type} should have without_detaching");
        }
    }

    public function test_non_pivot_many_relation_record_has_no_pivot(): void
    {
        foreach (['HasMany', 'MorphMany', 'HasManyThrough'] as $type) {
            $result = $this->callPrivate('buildRelationMutationType', [$type]);
            $items = $result->items;
            $this->assertArrayNotHasKey('pivot', $items->properties, "{$type} should not have pivot");
            $this->assertArrayNotHasKey('without_detaching', $items->properties, "{$type} should not have without_detaching");
        }
    }

    public function test_single_relation_record_has_no_pivot(): void
    {
        $result = $this->callPrivate('buildRelationMutationType', ['BelongsTo']);

        $this->assertInstanceOf(ObjectType::class, $result);
        $this->assertArrayNotHasKey('pivot', $result->properties);
        $this->assertArrayNotHasKey('without_detaching', $result->properties);
    }

    public function test_relation_key_is_mixed_type(): void
    {
        // key must accept both a single integer and an array of integers.
        $single = $this->callPrivate('buildRelationMutationType', ['BelongsTo']);
        $this->assertInstanceOf(MixedType::class, $single->properties['key']);

        $many = $this->callPrivate('buildRelationMutationType', ['BelongsToMany']);
        $this->assertInstanceOf(MixedType::class, $many->items->properties['key']);
    }

    // -------------------------------------------------------------------------
    // buildRelationsDescription
    // -------------------------------------------------------------------------

    public function test_build_relations_description_returns_empty_string_for_no_relations(): void
    {
        $this->assertSame('', $this->callPrivate('buildRelationsDescription', [[]]));
    }

    public function test_build_relations_description_lists_all_relations(): void
    {
        $relations = [
            ['name' => 'posts',    'type' => 'HasMany'],
            ['name' => 'category', 'type' => 'BelongsTo'],
        ];

        $desc = $this->callPrivate('buildRelationsDescription', [$relations]);

        $this->assertStringContainsString('posts', $desc);
        $this->assertStringContainsString('HasMany', $desc);
        $this->assertStringContainsString('category', $desc);
        $this->assertStringContainsString('BelongsTo', $desc);
    }

    // -------------------------------------------------------------------------
    // Search schema structure
    // -------------------------------------------------------------------------

    public function test_search_schema_includes_not_like_operator(): void
    {
        $operation = $this->makeOperation();
        $this->callPrivate('documentSearch', [$operation, ['id', 'name'], [], 'Model']);

        $schema = $this->extractSearchSchema($operation);

        $filtersItems = $schema->properties['filters']->items;
        $operatorEnum = $filtersItems->properties['operator']->enum;

        $this->assertContains('not like', $operatorEnum);
        $this->assertContains('like', $operatorEnum);
    }

    public function test_search_schema_value_is_mixed_type(): void
    {
        $operation = $this->makeOperation();
        $this->callPrivate('documentSearch', [$operation, ['id'], [], 'Model']);

        $schema = $this->extractSearchSchema($operation);
        $filterItems = $schema->properties['filters']->items;

        $this->assertInstanceOf(MixedType::class, $filterItems->properties['value']);
    }

    public function test_search_schema_includes_text_aggregates_instructions_gates(): void
    {
        $operation = $this->makeOperation();
        $this->callPrivate('documentSearch', [$operation, ['id'], [], 'Model']);

        $schema = $this->extractSearchSchema($operation);

        $this->assertArrayHasKey('text', $schema->properties);
        $this->assertArrayHasKey('aggregates', $schema->properties);
        $this->assertArrayHasKey('instructions', $schema->properties);
        $this->assertArrayHasKey('gates', $schema->properties);
    }

    // -------------------------------------------------------------------------
    // Mutate schema structure
    // -------------------------------------------------------------------------

    public function test_mutate_schema_uses_update_rules_for_type_inference(): void
    {
        // Provide a field only in updateRules and confirm it appears in the schema.
        $rules = [
            'age' => ['all' => [], 'create' => [], 'update' => ['integer']],
        ];

        $operation = $this->makeOperation();
        $this->callPrivate('documentMutate', [$operation, ['age'], [], $rules, 'Model']);

        $schema = $this->extractMutateSchema($operation);
        $attributes = $schema->items->properties['attributes'];

        $this->assertArrayHasKey('age', $attributes->properties);
        $this->assertInstanceOf(IntegerType::class, $attributes->properties['age']);
    }

    // -------------------------------------------------------------------------
    // Private helpers for schema extraction
    // -------------------------------------------------------------------------

    private function makeOperation(): Operation
    {
        return new Operation('post');
    }

    private function extractSearchSchema(Operation $operation): ObjectType
    {
        $body = $operation->requestBodyObject->content['application/json']->type;

        return $body->properties['search'];
    }

    private function extractMutateSchema(Operation $operation): ArrayType
    {
        $body = $operation->requestBodyObject->content['application/json']->type;

        return $body->properties['mutate'];
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TextSearchValidationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_refuses_a_text_search_on_a_model_that_is_not_searchable(): void
    {
        $response = $this->postJson('/api/categories/search', [
            'search' => ['text' => ['value' => 'lighting']],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.text field is prohibited.',
            'errors' => ['search.text' => ['The search.text field is prohibited.']],
        ]);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidTextSearches')]
    public function it_rejects_what_a_text_search_cannot_run(array $search, array $errors): void
    {
        $response = $this->postJson('/api/products/search', ['search' => $search]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function invalidTextSearches(): array
    {
        $lamp = ['value' => 'lamp'];

        return [
            'an operator the index does not run' => [['text' => $lamp, 'filters' => [['field' => 'price', 'operator' => '>=', 'value' => 39]]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'a filter outside the scout fields' => [['text' => $lamp, 'filters' => [['field' => 'name', 'value' => 'Desk lamp']]], [
                'search.filters.0.field' => ['The selected search.filters.0.field is invalid.'],
            ]],
            'a nested filter' => [['text' => $lamp, 'filters' => [['nested' => [['field' => 'price', 'value' => 39]]]]], [
                'search.filters.0.nested' => ['The search.filters.0.nested field is prohibited.'],
            ]],
            'an or filter' => [['text' => $lamp, 'filters' => [['field' => 'price', 'value' => 39, 'type' => 'or']]], [
                'search.filters.0.type' => ['The search.filters.0.type field is prohibited.'],
            ]],
            'a sort outside the scout fields' => [['text' => $lamp, 'sorts' => [['field' => 'name']]], [
                'search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.'],
            ]],
            'an instruction not declared for text search' => [['text' => $lamp, 'instructions' => [['name' => 'discounted']]], [
                'search.instructions.0.name' => ['The selected search.instructions.0.name is invalid.'],
            ]],
            'an unknown trashed value' => [['text' => ['value' => 'lamp', 'trashed' => 'unknown']], [
                'search.text.trashed' => ['The selected search.text.trashed is invalid.'],
            ]],
            'a trashed value without text' => [['text' => ['trashed' => 'with']], [
                'search.text.trashed' => ['The search.text.trashed field is prohibited.'],
            ]],
            'a trashed value next to a blank text' => [['text' => ['value' => ' ', 'trashed' => 'only']], [
                'search.text.trashed' => ['The search.text.trashed field is prohibited.'],
            ]],
            'a text longer than 255 characters' => [['text' => ['value' => str_repeat('a', 256)]], [
                'search.text.value' => ['The search.text.value field must not be greater than 255 characters.'],
            ]],
            'a text that is not a string' => [['text' => ['value' => ['lamp']]], [
                'search.text.value' => ['The search.text.value field must be a string.'],
            ]],
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidIncludesAndAggregates')]
    public function it_validates_the_includes_and_aggregates_of_a_text_search_as_sql(array $search, array $errors): void
    {
        $response = $this->postJson('/api/products/search', [
            'search' => ['text' => ['value' => 'lamp'], ...$search],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{array<string, list<array<string, mixed>>>, array<string, list<string>>}> */
    public static function invalidIncludesAndAggregates(): array
    {
        return [
            'an include filter on an undeclared column' => [['includes' => [['relation' => 'category', 'filters' => [['field' => 'created_at', 'value' => '2026-01-01']]]]], [
                'search.includes.0.filters.0.field' => ['The selected search.includes.0.filters.0.field is invalid.'],
            ]],
            'an include filter field that is not a string' => [['includes' => [['relation' => 'category', 'filters' => [['field' => ['name'], 'value' => 'Lighting']]]]], [
                'search.includes.0.filters.0.field' => ['The search.includes.0.filters.0.field field must be a string.', 'The selected search.includes.0.filters.0.field is invalid.'],
            ]],
            'an include select of an undeclared column' => [['includes' => [['relation' => 'category', 'selects' => [['field' => 'created_at']]]]], [
                'search.includes.0.selects.0.field' => ['The selected search.includes.0.selects.0.field is invalid.'],
            ]],
            'an include of an unknown relation' => [['includes' => [['relation' => 'supplier']]], [
                'search.includes.0.relation' => ['The relation is not valid or allowed for this resource.'],
            ]],
            'an include of an unknown nested relation' => [['includes' => [['relation' => 'supplier.country']]], [
                'search.includes.0.relation' => ['The relation is not valid or allowed for this resource.'],
            ]],
            'an include without relation' => [['includes' => [['limit' => 10]]], [
                'search.includes.0.relation' => ['The search.includes.0.relation field is required.'],
            ]],
            'an include relation that is not a string' => [['includes' => [['relation' => ['category']]]], [
                'search.includes.0.relation' => ['The relation is not valid or allowed for this resource.'],
            ]],
            'an aggregate without relation' => [['aggregates' => [['type' => 'count']]], [
                'search.aggregates.0.relation' => ['The search.aggregates.0.relation field is required.'],
            ]],
            'an aggregate filter field that is not a string' => [['aggregates' => [['relation' => 'category', 'type' => 'count', 'filters' => [['field' => ['name'], 'value' => 'Lighting']]]]], [
                'search.aggregates.0.filters.0.field' => ['The search.aggregates.0.filters.0.field field must be a string.', 'The selected search.aggregates.0.filters.0.field is invalid.'],
            ]],
        ];
    }

    #[Test]
    #[DataProvider('queryBuilderMethods')]
    public function it_refuses_an_include_named_after_a_query_builder_method(string $method): void
    {
        Product::factory()->create(['name' => 'Desk lamp']);

        $response = $this->postJson('/api/products/search', [
            'search' => ['text' => ['value' => 'lamp'], 'includes' => [['relation' => $method]]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.includes.0.relation' => ['The relation is not valid or allowed for this resource.'],
        ]);
        $this->assertSame(['Desk lamp'], Product::query()->pluck('name')->all());
    }

    /** @return array<string, array{string}> */
    public static function queryBuilderMethods(): array
    {
        return [
            'truncate' => ['truncate'],
            'delete' => ['delete'],
            'update' => ['update'],
        ];
    }

    #[Test]
    public function it_loads_the_includes_and_aggregates_of_a_text_search_with_sql_filters(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $lamp = Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);

        $response = $this->postJson('/api/products/search', [
            'search' => [
                'text' => ['value' => 'lamp'],
                'includes' => [['relation' => 'category', 'filters' => [['field' => 'name', 'operator' => 'like', 'value' => 'Light%']]]],
                'aggregates' => [['relation' => 'category', 'type' => 'count', 'filters' => [['field' => 'name', 'operator' => 'like', 'value' => 'Light%']]]],
            ],
        ]);

        $response->assertOk()->assertJsonPath('data', [[
            'id' => $lamp->id,
            'category_id' => $lighting->id,
            'name' => 'Desk lamp',
            'price' => 39,
            'category_count' => 1,
            'category' => ['id' => $lighting->id, 'name' => 'Lighting'],
        ]]);
    }
}

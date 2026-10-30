<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DottedKeysTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('searches')]
    public function it_validates_a_search_entry_whose_key_holds_a_dot(array $search, array $errors): void
    {
        $response = $this->postJson('/api/products/search', ['search' => $search]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function searches(): array
    {
        $undeclared = ['field' => 'created_at', 'value' => '2001-01-01'];

        return [
            'a filter' => [['filters' => ['a.b' => $undeclared]], [
                'search.filters.a.b.field' => ["The 'field' field is not valid."],
            ]],
            'a filter whose key ends with a dot' => [['filters' => ['a.' => $undeclared]], [
                'search.filters.a..field' => ["The 'field' field is not valid."],
            ]],
            'a nested filter' => [['filters' => [['nested' => ['a.b' => $undeclared]]]], [
                'search.filters.0.nested.a.b.field' => ["The 'field' field is not valid."],
            ]],
            'a filter of an include' => [['includes' => [['relation' => 'category', 'filters' => ['a.b' => $undeclared]]]], [
                'search.includes.0.filters.a.b.field' => ["The 'field' field is not valid."],
            ]],
            'a select' => [['selects' => ['a.b' => ['field' => 'created_at']]], [
                'search.selects.a.b.field' => ['The selected search.selects.a.b.field is invalid.'],
            ]],
            'a sort' => [['sorts' => ['a.b' => ['field' => 'created_at']]], [
                'search.sorts.a.b.field' => ['The selected search.sorts.a.b.field is invalid.'],
            ]],
        ];
    }

    #[Test]
    public function it_rejects_an_entry_whose_key_holds_a_wildcard(): void
    {
        $response = $this->postJson('/api/products/search', ['search' => ['filters' => [
            '*' => ['field' => 'created_at', 'value' => '2001-01-01'],
            'named' => ['field' => 'name', 'value' => 'Desk lamp'],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters' => ['The * key is not valid for the search.filters field.'],
        ]);
    }

    #[Test]
    public function it_validates_a_mutation_whose_key_holds_a_dot(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);

        $response = $this->postJson('/api/products/mutate', ['mutate' => ['a.b' => [
            'operation' => 'create',
            'attributes' => ['name' => 'Desk lamp', 'price' => 39, 'category_id' => $lighting->id, 'created_at' => '2001-01-01 00:00:00'],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.a.b.attributes' => ['The created_at key is not valid for the mutate.a.b.attributes field.'],
        ]);
        $this->assertSame(0, Product::query()->count());
    }

    #[Test]
    public function it_validates_a_to_many_entry_whose_key_holds_a_dot(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Lighting'],
            'relations' => ['products' => ['a.b' => [
                'operation' => 'create',
                'attributes' => ['name' => 'Desk lamp', 'price' => 39, 'created_at' => '2001-01-01 00:00:00'],
            ]]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.products.a.b.attributes' => ['The created_at key is not valid for the mutate.0.relations.products.a.b.attributes field.'],
        ]);
        $this->assertSame(0, Category::query()->count());
        $this->assertSame(0, Product::query()->count());
    }

    #[Test]
    public function it_validates_the_instruction_sent_and_not_a_root_key_holding_its_path(): void
    {
        $response = $this->postJson('/api/posts/search', [
            'search' => ['instructions' => [['name' => 'in-status', 'fields' => [['name' => 'status', 'value' => 'archived']]]]],
            'search.instructions.0' => ['name' => 'in-status', 'fields' => [['name' => 'status', 'value' => 'draft']]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.instructions.0.fields.status' => ['The selected status is invalid.'],
        ]);
    }

    #[Test]
    public function it_validates_the_scope_sent_and_not_a_root_key_holding_its_path(): void
    {
        $response = $this->postJson('/api/posts/search', [
            'search' => ['scopes' => [['name' => 'viewedBetween', 'parameters' => [0, 5]]]],
            'search.scopes.0' => ['name' => 'popular', 'parameters' => [0]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.scopes.0.parameters.0' => ['The search.scopes.0.parameters.0 field must be at least 1.'],
        ]);
    }
}

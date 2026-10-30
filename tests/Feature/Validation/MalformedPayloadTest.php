<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MalformedPayloadTest extends PackageTestCase
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
    #[DataProvider('malformedSearches')]
    public function it_rejects_a_malformed_search(array $search, array $errors): void
    {
        $response = $this->postJson('/api/products/search', ['search' => $search]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function malformedSearches(): array
    {
        return [
            'an include without a relation' => [['includes' => [[]]], [
                'search.includes.0.relation' => ['The search.includes.0.relation field is required.'],
            ]],
            'an include that is not an object' => [['includes' => ['category']], [
                'search.includes.0' => ['The search.includes.0 field must be an array.'],
            ]],
            'an include with a null relation' => [['includes' => [['relation' => null]]], [
                'search.includes.0.relation' => ['The search.includes.0.relation field is required.'],
            ]],
            'a nested include without a relation' => [['includes' => [['relation' => 'category', 'includes' => [[]]]]], [
                'search.includes.0.includes.0.relation' => ['The search.includes.0.includes.0.relation field is required.'],
            ]],
            'an aggregate without a relation or a type' => [['aggregates' => [[]]], [
                'search.aggregates.0.relation' => ['The search.aggregates.0.relation field is required.'],
                'search.aggregates.0.type' => ['The search.aggregates.0.type field is required.'],
            ]],
            'a filter that is not an object' => [['filters' => ['name']], [
                'search.filters.0' => ['The search.filters.0 field must be an array.'],
            ]],
        ];
    }

    /**
     * @param  list<mixed>  $mutations
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('malformedProductMutations')]
    public function it_rejects_a_malformed_product_mutation(array $mutations, array $errors): void
    {
        $response = $this->postJson('/api/products/mutate', ['mutate' => $mutations]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame(0, Product::query()->count());
    }

    /** @return array<string, array{list<mixed>, array<string, list<string>>}> */
    public static function malformedProductMutations(): array
    {
        return [
            'a mutation without an operation' => [[[]], [
                'mutate.0.operation' => ['The mutate.0.operation field is required.'],
            ]],
            'a mutation that is not an object' => [['create'], [
                'mutate.0' => ['The mutate.0 field must be an array.'],
            ]],
            'a to-one relation given a list' => [[['operation' => 'create', 'attributes' => ['name' => 'Desk lamp', 'price' => 39], 'relations' => [
                'category' => [['operation' => 'attach', 'key' => 1]],
            ]]], [
                'mutate.0.relations.category.operation' => ['The mutate.0.relations.category.operation field is required.'],
            ]],
            'a to-one relation given null' => [[['operation' => 'create', 'attributes' => ['name' => 'Desk lamp', 'price' => 39], 'relations' => [
                'category' => null,
            ]]], [
                'mutate.0.relations.category' => ['The mutate.0.relations.category field must be an array.'],
            ]],
        ];
    }

    /**
     * @param  list<mixed>  $products
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('malformedToManyRelations')]
    public function it_rejects_a_malformed_to_many_relation(array $products, array $errors): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Lighting'], 'relations' => ['products' => $products]],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame(0, Category::query()->count());
    }

    /** @return array<string, array{array<array-key, mixed>, array<string, list<string>>}> */
    public static function malformedToManyRelations(): array
    {
        return [
            'a to-many relation given an object' => [['operation' => 'create', 'attributes' => ['name' => 'Desk lamp', 'price' => 39]], [
                'mutate.0.relations.products.operation' => ['The mutate.0.relations.products.operation field must be an array.'],
                'mutate.0.relations.products.attributes.operation' => ['The mutate.0.relations.products.attributes.operation field is required.'],
            ]],
            'to-many entries that are malformed one by one' => [['create', [], ['operation' => 'replace']], [
                'mutate.0.relations.products.0' => ['The mutate.0.relations.products.0 field must be an array.'],
                'mutate.0.relations.products.1.operation' => ['The mutate.0.relations.products.1.operation field is required.'],
                'mutate.0.relations.products.2.operation' => ['The selected mutate.0.relations.products.2.operation is invalid.'],
            ]],
        ];
    }

    #[Test]
    public function it_rejects_an_operation_whose_search_is_not_an_object(): void
    {
        $post = Post::factory()->create(['status' => PostStatus::Draft]);

        $response = $this->postJson('/api/posts/actions/publish-posts', ['search' => 'boom']);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search field must be an array.',
            'errors' => ['search' => ['The search field must be an array.']],
        ]);
        $this->assertSame(PostStatus::Draft, $post->fresh()?->status);
    }
}

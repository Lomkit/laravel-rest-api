<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class DepthLimitsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[Test]
    public function it_refuses_includes_deeper_than_the_limit(): void
    {
        config(['rest.limits.include_depth' => 1]);

        $response = $this->postJson('/api/posts/search', ['search' => ['includes' => [
            ['relation' => 'author', 'includes' => [['relation' => 'posts']]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson($this->prohibited('search.includes.0.includes'));
    }

    #[Test]
    public function it_includes_three_levels_by_default(): void
    {
        $response = $this->postJson('/api/products/search', ['search' => ['includes' => [
            ['relation' => 'category', 'includes' => [['relation' => 'products', 'includes' => [['relation' => 'category']]]]],
        ]]]);

        $response->assertOk();
    }

    #[Test]
    public function it_refuses_a_fourth_level_of_includes_by_default(): void
    {
        $response = $this->postJson('/api/categories/search', ['search' => ['includes' => [
            ['relation' => 'products', 'includes' => [
                ['relation' => 'category', 'includes' => [['relation' => 'products', 'includes' => [['relation' => 'category']]]]],
            ]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson($this->prohibited('search.includes.0.includes.0.includes.0.includes'));
    }

    #[Test]
    public function it_refuses_relations_deeper_than_the_limit(): void
    {
        config(['rest.limits.mutation_depth' => 1]);

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Lighting'],
            'relations' => ['products' => [[
                'operation' => 'create',
                'attributes' => ['name' => 'Desk lamp', 'price' => 39],
                'relations' => ['category' => ['operation' => 'create', 'attributes' => ['name' => 'Desks']]],
            ]]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson($this->prohibited('mutate.0.relations.products.0.relations'));
        $this->assertSame(0, Category::count());
    }

    #[Test]
    public function it_refuses_a_fourth_level_of_relations_by_default(): void
    {
        $category = fn (array $relations): array => ['operation' => 'create', 'attributes' => ['name' => 'Lighting'], 'relations' => $relations];
        $product = fn (array $relations): array => ['operation' => 'create', 'attributes' => ['name' => 'Desk lamp', 'price' => 39], 'relations' => $relations];

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            $category(['products' => [$product(['category' => $category(['products' => [$product(['category' => $category([])])]])])]]),
        ]]);

        $response->assertUnprocessable()->assertExactJson($this->prohibited('mutate.0.relations.products.0.relations.category.relations.products.0.relations'));
        $this->assertSame(0, Category::count());
        $this->assertSame(0, Product::count());
    }

    #[Test]
    public function it_reads_nothing_under_a_filter_group_nested_too_deep(): void
    {
        config(['rest.search.max_nesting_depth' => 1]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['nested' => [['nested' => [['field' => 'secret']]]]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson($this->prohibited('search.filters.0.nested.0.nested'));
    }

    /** @return array{message: string, errors: array<string, list<string>>} */
    private function prohibited(string $key): array
    {
        $message = "The {$key} field is prohibited.";

        return ['message' => $message, 'errors' => [$key => [$message]]];
    }
}

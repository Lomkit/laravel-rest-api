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
        config(['rest.limits.depths.includes' => 1]);

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
    public function it_counts_each_relation_of_a_dotted_include(): void
    {
        config(['rest.limits.depths.includes' => 2]);
        $message = 'The search.includes.0.relation field goes deeper than the limit of 2 include levels.';

        $response = $this->postJson('/api/posts/search', ['search' => ['includes' => [['relation' => 'author.posts.author']]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.includes.0.relation' => [$message]]]);
    }

    #[Test]
    public function it_refuses_includes_below_a_dotted_include_at_the_limit(): void
    {
        config(['rest.limits.depths.includes' => 2]);

        $response = $this->postJson('/api/posts/search', ['search' => ['includes' => [
            ['relation' => 'author.posts', 'includes' => [['relation' => 'author']]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson($this->prohibited('search.includes.0.includes'));
    }

    #[Test]
    public function it_includes_a_dotted_relation_up_to_the_default_depth(): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => ['includes' => [['relation' => 'author.posts.author']]]]);

        $response->assertOk();
    }

    #[Test]
    public function it_refuses_relations_deeper_than_the_limit(): void
    {
        config(['rest.limits.depths.mutations' => 1]);

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
        config(['rest.limits.depths.filters' => 1]);

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

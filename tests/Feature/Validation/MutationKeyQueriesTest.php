<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

class MutationKeyQueriesTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_checks_the_keys_of_a_resource_with_one_query(): void
    {
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);

        foreach (range(1, 100) as $key) {
            Product::factory()->create(['id' => $key, 'category_id' => 1]);
        }

        $this->captureQueries();

        $response = $this->postJson('/api/products/mutate', ['mutate' => array_map(
            fn (int $key): array => ['operation' => 'update', 'key' => $key, 'attributes' => ['price' => 10]],
            range(1, 100),
        )], ['Precognition' => 'true']);

        $response->assertNoContent();
        $this->assertCount(1, $this->statements);
        $this->assertSame($this->statements, [$this->readsOf('products')[0]]);
        $this->assertStringContainsString('in (1, 2, 3, ', $this->statements[0][0]);
        $this->assertStringContainsString(', 100)', $this->statements[0][0]);
    }

    #[Test]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_checks_the_keys_of_each_resource_class_with_one_query(): void
    {
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Category::factory()->create(['id' => 2, 'name' => 'Seating']);
        Product::factory()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp']);
        Product::factory()->create(['id' => 2, 'category_id' => 1, 'name' => 'Floor lamp']);
        Product::factory()->create(['id' => 3, 'category_id' => 1, 'name' => 'Office chair']);

        $this->captureQueries();

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => [
                'products' => [['operation' => 'attach', 'key' => [1, 2]], ['operation' => 'detach', 'key' => 3]],
                'featuredProducts' => [['operation' => 'attach', 'key' => [1]]],
            ]],
            ['operation' => 'update', 'key' => 2, 'relations' => ['products' => [['operation' => 'attach', 'key' => [3]]]]],
        ]], ['Precognition' => 'true']);

        $response->assertNoContent();
        $this->assertCount(1, $this->readsOf('categories'));
        $this->assertCount(1, $this->readsOf('products'));
        $this->assertCount(1, $this->readsOf('featured_products'));
        $this->assertCount(3, $this->statements);
    }

    #[Test]
    public function it_runs_no_key_query_for_a_key_list_over_its_limit(): void
    {
        config(['rest.limits.sizes.values' => 2]);
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Product::factory()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp']);

        $this->captureQueries();

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => ['products' => [['operation' => 'attach', 'key' => [1, 2, 3]]]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations.products.0.key' => ['The mutate.0.relations.products.0.key field must not have more than 2 items.']]);
        $this->assertSame([], $this->readsOf('products'));
    }
}

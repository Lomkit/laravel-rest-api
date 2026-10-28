<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class SearchProductsTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_returns_the_products_matching_a_filter(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $deskLamp = Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 129]);

        $response = $this->postJson('/api/products/search', [
            'search' => [
                'filters' => [
                    ['field' => 'price', 'operator' => '<', 'value' => 100],
                ],
            ],
        ]);

        $response->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => [
                ['id' => $deskLamp->id, 'category_id' => $lighting->id, 'name' => 'Desk lamp', 'price' => 39],
            ],
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => 1,
            'total' => 1,
            'meta' => [],
        ]);
    }

    #[Test]
    public function it_includes_the_category_of_each_product(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $deskLamp = Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        $floorLamp = Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 129]);

        $response = $this->postJson('/api/products/search', [
            'search' => [
                'includes' => [
                    ['relation' => 'category'],
                ],
            ],
        ]);

        $category = ['id' => $lighting->id, 'name' => 'Lighting'];

        $response->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => [
                ['id' => $floorLamp->id, 'category_id' => $lighting->id, 'name' => 'Floor lamp', 'price' => 129, 'category' => $category],
                ['id' => $deskLamp->id, 'category_id' => $lighting->id, 'name' => 'Desk lamp', 'price' => 39, 'category' => $category],
            ],
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => 2,
            'total' => 2,
            'meta' => [],
        ]);
    }
}

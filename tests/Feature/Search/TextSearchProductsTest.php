<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class TextSearchProductsTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_returns_the_products_matching_a_text_search(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        $floorLamp = Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 129]);

        $response = $this->postJson('/api/products/search', [
            'search' => [
                'text' => ['value' => 'floor'],
            ],
        ]);

        $response->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => [
                ['id' => $floorLamp->id, 'category_id' => $lighting->id, 'name' => 'Floor lamp', 'price' => 129],
            ],
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => 1,
            'total' => 1,
            'meta' => [],
        ]);
    }
}

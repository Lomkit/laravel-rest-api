<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Fields;

use Illuminate\Support\Facades\DB;

trait SeedsCatalog
{
    private const array PRODUCTS = [
        ['id' => 3, 'category_id' => 2, 'name' => 'Stool', 'price' => 45],
        ['id' => 2, 'category_id' => 1, 'name' => 'Floor lamp', 'price' => 129],
        ['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39],
    ];

    private const array PRICELESS_PRODUCTS = [
        ['id' => 3, 'category_id' => 2, 'name' => 'Stool'],
        ['id' => 2, 'category_id' => 1, 'name' => 'Floor lamp'],
        ['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp'],
    ];

    private function seedCatalog(): void
    {
        DB::table('categories')->insert([['id' => 1, 'name' => 'Lighting'], ['id' => 2, 'name' => 'Seating']]);
        DB::table('products')->insert(self::PRODUCTS);
        DB::table('featured_products')->insert([['category_id' => 1, 'product_id' => 2, 'price' => 99]]);
    }
}

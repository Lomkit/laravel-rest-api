<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => 'Lighting',
        ];
    }
}

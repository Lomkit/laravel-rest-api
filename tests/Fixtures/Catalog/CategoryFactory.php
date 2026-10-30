<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
#[UseModel(Category::class)]
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Lighting',
        ];
    }
}

<?php

namespace Lomkit\Rest\Tests\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lomkit\Rest\Tests\Support\Models\HasManyThroughRelation;

class HasManyThroughRelationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model|TModel>
     */
    protected $model = HasManyThroughRelation::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'number' => fake()->numberBetween(-5000, 5000),
        ];
    }
}

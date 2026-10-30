<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tag> */
#[UseModel(Tag::class)]
class TagFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ];
    }
}

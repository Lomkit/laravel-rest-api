<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
#[UseModel(Post::class)]
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Annual report',
            'summary' => null,
            'status' => PostStatus::Draft,
            'featured' => false,
            'views' => 0,
            'rating' => null,
            'published_on' => '2026-01-01',
            'published_at' => null,
            'metadata' => null,
        ];
    }
}

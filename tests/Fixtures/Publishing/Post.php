<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UseFactory(PostFactory::class)]
class Post extends Model
{
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'featured' => 'boolean',
            'views' => 'integer',
            'rating' => 'float',
            'published_on' => 'date',
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}

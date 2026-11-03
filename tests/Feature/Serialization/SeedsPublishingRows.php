<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Illuminate\Support\Carbon;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;

trait SeedsPublishingRows
{
    private function seedPublishingRows(): void
    {
        Carbon::setTestNow('2026-01-02 00:00:00');

        $ada = Author::create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-ada', 'settings' => null]);
        Author::create(['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'token-grace', 'settings' => null]);
        $this->createPost(1, 1, 'Engines', PostStatus::Published, false, 150);
        $this->createPost(2, 1, 'Notes', PostStatus::Draft, true, 5);
        $this->createPost(3, null, 'Orphans', PostStatus::Published, false, 20);
        Tag::create(['id' => 1, 'name' => 'Mathematics', 'slug' => 'mathematics']);
        $ada->tags()->attach(1);
    }

    private function createPost(int $id, ?int $authorId, string $title, PostStatus $status, bool $featured, int $views): void
    {
        Post::create([
            'id' => $id,
            'author_id' => $authorId,
            'title' => $title,
            'summary' => null,
            'status' => $status,
            'featured' => $featured,
            'views' => $views,
            'rating' => null,
            'published_on' => '2026-01-01',
            'published_at' => null,
            'metadata' => null,
        ]);
    }
}

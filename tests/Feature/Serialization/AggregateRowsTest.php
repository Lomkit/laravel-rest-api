<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class AggregateRowsTest extends PackageTestCase
{
    use SeedsPublishingRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPublishingRows();
        Post::create([
            'id' => 4,
            'author_id' => 2,
            'title' => 'Compilers',
            'summary' => null,
            'status' => PostStatus::Published,
            'featured' => true,
            'views' => 20,
            'rating' => null,
            'published_on' => '2026-01-01',
            'published_at' => null,
            'metadata' => null,
        ]);
    }

    #[Test]
    public function it_keeps_the_columns_of_a_row_next_to_an_aggregate_named_like_one(): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => [
            'selects' => [['field' => 'id']],
            'aggregates' => [['relation' => 'author', 'type' => 'count', 'alias' => 'author_id']],
            'includes' => [['relation' => 'author', 'selects' => [['field' => 'first_name']]]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'author_id' => 1, 'author' => ['first_name' => 'Ada']],
            ['id' => 2, 'author_id' => 1, 'author' => ['first_name' => 'Ada']],
            ['id' => 3, 'author_id' => 0, 'author' => null],
            ['id' => 4, 'author_id' => 1, 'author' => ['first_name' => 'Grace']],
        ]);
    }

    #[Test]
    public function it_returns_an_aggregate_named_like_a_cast_column_as_computed(): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'aggregates' => [['relation' => 'posts', 'type' => 'count', 'alias' => 'deleted_at']],
            'sorts' => [['field' => 'first_name']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'deleted_at' => 2],
            ['id' => 2, 'deleted_at' => 1],
        ]);
    }

    #[Test]
    public function it_returns_an_aggregate_under_an_alias_of_255_characters(): void
    {
        $alias = str_repeat('d', 255);

        $response = $this->postJson('/api/authors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'aggregates' => [['relation' => 'posts', 'type' => 'count', 'alias' => $alias]],
            'sorts' => [['field' => 'first_name']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.'.$alias, [2, 1]);
    }

    #[Test]
    public function it_returns_an_aggregate_whose_key_the_model_lists_as_visible(): void
    {
        Author::retrieved(static fn (Author $author): Author => $author->setVisible(['id', 'first_name', 'posts_count']));

        $response = $this->postJson('/api/authors/search', ['search' => [
            'aggregates' => [['relation' => 'posts', 'type' => 'count']],
            'sorts' => [['field' => 'first_name']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'first_name' => 'Ada', 'posts_count' => 2],
            ['id' => 2, 'first_name' => 'Grace', 'posts_count' => 1],
        ]);
    }

    #[Test]
    public function it_leaves_out_an_aggregate_whose_key_the_model_hides(): void
    {
        Author::retrieved(static fn (Author $author): Author => $author->setHidden(['posts_count']));

        $response = $this->postJson('/api/authors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'aggregates' => [['relation' => 'posts', 'type' => 'count']],
            'sorts' => [['field' => 'first_name']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1],
            ['id' => 2],
        ]);
    }
}

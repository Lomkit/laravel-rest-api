<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Carbon;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\QualifiedOrderAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\QualifiedOrderPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class QualifiedDefaultOrderTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-01-02 00:00:00');

        $ada = Author::factory()->create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => null]);
        $grace = Author::factory()->create(['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'settings' => null]);
        $alan = Author::factory()->create(['id' => 3, 'first_name' => 'Alan', 'last_name' => 'Turing', 'settings' => null]);
        Author::factory()->create(['id' => 4, 'first_name' => 'Edsger', 'last_name' => 'Dijkstra', 'settings' => null, 'deleted_at' => '2026-01-01 00:00:00']);
        Post::factory()->create(['id' => 1, 'author_id' => 1, 'title' => 'Engines', 'status' => PostStatus::Published, 'featured' => false, 'views' => 150]);
        Post::factory()->create(['id' => 2, 'author_id' => 1, 'title' => 'Notes', 'status' => PostStatus::Draft, 'featured' => true, 'views' => 5]);
        Post::factory()->create(['id' => 3, 'author_id' => 2, 'title' => 'Compilers', 'status' => PostStatus::Published, 'featured' => true, 'views' => 20]);
        Post::factory()->create(['id' => 4, 'author_id' => 4, 'title' => 'Paths', 'status' => PostStatus::Published, 'featured' => false, 'views' => 40]);
        Tag::factory()->create(['id' => 1, 'name' => 'Mathematics', 'slug' => 'mathematics']);
        Tag::factory()->create(['id' => 2, 'name' => 'Navy', 'slug' => 'navy']);
        $ada->tags()->attach(1);
        $alan->tags()->attach(1);
        $grace->tags()->attach(2);
    }

    #[Test]
    public function it_orders_by_a_qualified_default_column(): void
    {
        Rest::resource('qualified-order-posts', QualifiedOrderPostController::class)->register();

        $response = $this->postJson('/qualified-order-posts/search', ['search' => (object) []]);

        $response->assertOk()->assertJsonPath('data.*.id', [2, 3, 4, 1]);
    }

    #[Test]
    public function it_orders_an_include_by_a_qualified_default_column(): void
    {
        Rest::resource('qualified-order-authors', QualifiedOrderAuthorController::class)->register();

        $response = $this->postJson('/qualified-order-authors/search', ['search' => [
            'includes' => [['relation' => 'posts']],
            'sorts' => [['field' => 'first_name']],
        ]]);

        $response->assertOk()
            ->assertJsonPath('data.0.first_name', 'Ada')
            ->assertJsonPath('data.0.posts.*.id', [2, 1]);
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class QueryBuilderBindingTest extends PackageTestCase
{
    /** @var list<class-string<Resource>> */
    private array $resolved = [];

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
    public function it_resolves_the_binding_once_for_the_searched_resource(): void
    {
        $this->recordTheQueryBuilderBinding();

        $response = $this->postJson('/api/authors/search', ['search' => [
            'includes' => [['relation' => 'posts']],
            'aggregates' => [['relation' => 'posts', 'type' => 'count']],
        ]]);

        $response->assertOk();
        $this->assertSame([AuthorResource::class], $this->resolved);
    }

    #[Test]
    public function it_resolves_the_query_builder_binding_for_an_action(): void
    {
        $this->recordTheQueryBuilderBinding();

        $response = $this->postJson('/api/posts/actions/publish-posts', ['search' => ['filters' => [['field' => 'status', 'value' => 'draft']]]]);

        $response->assertOk();
        $this->assertSame([PostResource::class], $this->resolved);
    }

    #[Test]
    public function it_resolves_the_query_builder_binding_once_for_a_mutation(): void
    {
        $this->recordTheQueryBuilderBinding();

        $response = $this->postJson('/api/posts/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 4, 'relations' => ['author' => ['operation' => 'attach', 'key' => 1]]],
        ]]);

        $response->assertOk();
        $this->assertSame([PostResource::class], $this->resolved);
        $this->assertSame(1, Post::query()
            ->findOrFail(4)
            ->getAttribute('author_id'));
    }

    private function recordTheQueryBuilderBinding(): void
    {
        $this->app->bind(QueryBuilder::class, function (Application $app, array $parameters): Builder {
            $this->resolved[] = $parameters['resource']::class;

            return new Builder($parameters['resource'], $parameters['query'] ?? null);
        });
    }
}

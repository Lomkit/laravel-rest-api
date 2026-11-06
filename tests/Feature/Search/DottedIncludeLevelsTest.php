<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\VisiblePostAuthorController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class DottedIncludeLevelsTest extends PackageTestCase
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
    public function it_keeps_the_constraints_of_an_include_listed_before_a_dotted_include(): void
    {
        $response = $this->searchAuthors('/api/authors/search', [
            ['relation' => 'posts', 'filters' => [['field' => 'status', 'value' => 'published']]],
            ['relation' => 'posts.author'],
        ]);

        $this->assertPostsPerAuthor($response, [[1], [], [3]]);
        $response->assertJsonPath('data.0.posts.0.author.id', 1);
    }

    #[Test]
    public function it_keeps_the_constraints_of_an_include_listed_after_a_dotted_include(): void
    {
        $response = $this->searchAuthors('/api/authors/search', [
            ['relation' => 'posts.author'],
            ['relation' => 'posts', 'filters' => [['field' => 'status', 'value' => 'published']]],
        ]);

        $this->assertPostsPerAuthor($response, [[1], [], [3]]);
        $response->assertJsonPath('data.0.posts.0.author.id', 1);
    }

    #[Test]
    public function it_keeps_the_aggregates_of_an_include_listed_before_a_dotted_include(): void
    {
        $response = $this->searchAuthors('/api/authors/search', [
            ['relation' => 'posts', 'aggregates' => [['relation' => 'author', 'type' => 'count']]],
            ['relation' => 'posts.author'],
        ]);

        $this->assertPostsPerAuthor($response, [[2, 1], [], [3]]);
        $response->assertJsonPath('data.0.posts.*.author_count', [1, 1]);
    }

    #[Test]
    public function it_keeps_the_perimeter_of_an_include_listed_before_a_dotted_include(): void
    {
        Rest::resource('visible-post-authors', VisiblePostAuthorController::class)->register();

        $response = $this->searchAuthors('/visible-post-authors/search', [
            ['relation' => 'posts'],
            ['relation' => 'posts.author'],
        ]);

        $this->assertPostsPerAuthor($response, [[1], [], [3]]);
    }

    /** @param list<array<string, mixed>> $includes */
    private function searchAuthors(string $uri, array $includes): TestResponse
    {
        return $this->postJson($uri, ['search' => ['includes' => $includes, 'sorts' => [['field' => 'first_name']]]]);
    }

    /** @param list<list<int>> $posts */
    private function assertPostsPerAuthor(TestResponse $response, array $posts): void
    {
        $response->assertOk()->assertJsonPath('data.*.first_name', ['Ada', 'Alan', 'Grace']);

        foreach ($posts as $index => $ids) {
            $response->assertJsonPath("data.{$index}.posts.*.id", $ids);
        }
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ContextCheckingPostController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ContextReadingSearchRequest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\EarlyContextReadingSearchRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class SearchContextHookTest extends PackageTestCase
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
        ContextReadingSearchRequest::$read = [];

        Author::factory()->create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => null]);
        Author::factory()->create(['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'settings' => null]);
        $this->createPost(1, 1, 'Engines', PostStatus::Published, false, 150);
        $this->createPost(2, 1, 'Notes', PostStatus::Draft, true, 5);
        $this->createPost(3, 2, 'Compilers', PostStatus::Published, true, 20);
    }

    #[Test]
    public function it_refuses_a_search_that_searching_refuses(): void
    {
        Rest::resource('checked-posts', ContextCheckingPostController::class)->register();
        DB::enableQueryLog();

        $response = $this->postJson('/checked-posts/search', ['search' => ['filters' => [['field' => 'featured', 'value' => true]]]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The featured filter requires a status filter.',
            'errors' => ['search.filters' => ['The featured filter requires a status filter.']],
        ]);
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_runs_a_search_that_searching_accepts(): void
    {
        Rest::resource('checked-posts', ContextCheckingPostController::class)->register();

        $response = $this->postJson('/checked-posts/search', ['search' => ['filters' => [
            ['field' => 'featured', 'value' => true],
            ['field' => 'status', 'value' => 'published'],
        ]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [3]);
    }

    #[Test]
    public function it_builds_the_search_context_once_per_request(): void
    {
        $this->app->bind(SearchRequest::class, ContextReadingSearchRequest::class);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'views', 'operator' => '>', 'value' => 10]]]]);

        $response->assertOk();
        $this->assertCount(2, ContextReadingSearchRequest::$read);
        $this->assertSame(ContextReadingSearchRequest::$read[0], ContextReadingSearchRequest::$read[1]);
        $this->assertTrue(ContextReadingSearchRequest::$read[0]->filtersOn('views'));
    }

    #[Test]
    public function it_refuses_to_read_the_search_context_while_the_request_validates(): void
    {
        $this->app->bind(SearchRequest::class, EarlyContextReadingSearchRequest::class);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new LogicException('The search was read before the request passed validation.'));

        $this->postJson('/api/posts/search', ['search' => ['limit' => 10]]);
    }

    private function createPost(int $id, int $author, string $title, PostStatus $status, bool $featured, int $views): void
    {
        Post::factory()->create([
            'id' => $id,
            'author_id' => $author,
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

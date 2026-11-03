<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Carbon;
use LogicException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\InputEditingPostController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\LenientSearchRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class SearchFromPayloadTest extends PackageTestCase
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
    public function it_runs_the_validated_search_when_before_search_edits_the_input(): void
    {
        Rest::resource('input-editing-posts', InputEditingPostController::class)->register();

        $response = $this->postJson('/input-editing-posts/search', ['search' => [
            'filters' => [['field' => 'status', 'value' => 'published']],
            'sorts' => [['field' => 'id']],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('data.*.id', [1, 3, 4])
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 50);
    }

    #[Test]
    public function it_refuses_to_run_a_search_whose_validation_failed(): void
    {
        $this->app->bind(SearchRequest::class, LenientSearchRequest::class);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new LogicException('The search was read before the request passed validation.'));

        $this->postJson('/api/posts/search', ['search' => ['filter' => []]]);
    }
}

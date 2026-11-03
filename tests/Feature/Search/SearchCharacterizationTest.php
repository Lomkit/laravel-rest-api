<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SearchCharacterizationTest extends PackageTestCase
{
    use ComparesSearchSnapshots;

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
        $this->createPost(1, 1, 'Engines', PostStatus::Published, false, 150);
        $this->createPost(2, 1, 'Notes', PostStatus::Draft, true, 5);
        $this->createPost(3, 2, 'Compilers', PostStatus::Published, true, 20);
        $this->createPost(4, 4, 'Paths', PostStatus::Published, false, 40);
        Tag::factory()->create(['id' => 1, 'name' => 'Mathematics', 'slug' => 'mathematics']);
        Tag::factory()->create(['id' => 2, 'name' => 'Navy', 'slug' => 'navy']);
        $ada->tags()->attach(1);
        $alan->tags()->attach(1);
        $grace->tags()->attach(2);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searches')]
    public function it_keeps_the_results_and_queries_of_a_search(string $endpoint, array $search, string $snapshot): void
    {
        DB::enableQueryLog();

        $response = $this->postJson($endpoint, ['search' => $search]);

        $response->assertOk();
        $this->assertMatchesSnapshot($snapshot, $response);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function searches(): array
    {
        $authors = '/api/authors/search';
        $posts = '/api/posts/search';
        $draft = ['field' => 'status', 'value' => 'draft'];
        $published = ['field' => 'status', 'value' => 'published'];

        return [
            'nested filters' => [$posts, ['filters' => [
                ['field' => 'views', 'operator' => '>', 'value' => 10],
                ['type' => 'or', 'nested' => [$draft, ['field' => 'featured', 'value' => true]]],
            ]], 'nested-filters'],
            'a relation path' => [$posts, ['filters' => [['field' => 'author.first_name', 'value' => 'Ada']]], 'relation-path'],
            'a relation group' => [$authors, ['filters' => [['relation' => 'posts', 'exists' => false, 'nested' => [$draft]]]], 'relation-group'],
            'sorts with and without direction' => [$posts, ['sorts' => [['field' => 'featured', 'direction' => 'desc'], ['field' => 'title']]], 'sorts'],
            'a scope' => [$posts, ['scopes' => [['name' => 'popular']]], 'scope'],
            'an instruction' => [$posts, ['instructions' => [['name' => 'in-status', 'fields' => [['name' => 'status', 'value' => 'draft']]]]], 'instruction'],
            'native and nested includes' => [$authors, ['includes' => [[
                'relation' => 'posts',
                'filters' => [$published],
                'sorts' => [['field' => 'views', 'direction' => 'desc']],
                'includes' => [['relation' => 'author']],
            ]]], 'nested-includes'],
            'an aliased include' => [$authors, ['includes' => [
                ['relation' => 'posts', 'alias' => 'drafted', 'filters' => [$draft]],
                ['relation' => 'posts'],
            ]], 'aliased-include'],
            'a dotted include before its first level' => [$authors, ['includes' => [
                ['relation' => 'posts.author'],
                ['relation' => 'posts', 'filters' => [$published]],
            ]], 'dotted-include'],
            'a repeated include' => [$authors, ['includes' => [
                ['relation' => 'posts', 'filters' => [$draft]],
                ['relation' => 'posts', 'filters' => [$published]],
            ]], 'repeated-include'],
            'a dotted include and an explicit level with its own include' => [$authors, ['includes' => [
                ['relation' => 'posts.author'],
                ['relation' => 'posts', 'includes' => [['relation' => 'author']]],
            ]], 'dotted-and-explicit-include'],
            'an include with a limit' => [$authors, ['includes' => [['relation' => 'posts', 'limit' => 10, 'sorts' => [['field' => 'id']]]]], 'include-limit'],
            'aggregates with filters' => [$authors, ['aggregates' => [
                ['relation' => 'posts', 'type' => 'count'],
                ['relation' => 'posts', 'type' => 'max', 'field' => 'views', 'filters' => [$published]],
                ['relation' => 'posts', 'type' => 'sum', 'field' => 'views', 'alias' => 'total_views'],
            ]], 'aggregates'],
            'trashed' => [$authors, ['trashed' => 'with', 'sorts' => [['field' => 'first_name']]], 'trashed'],
        ];
    }

    #[Test]
    public function it_keeps_the_rows_and_queries_of_an_action_search(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/posts/actions/publish-posts', ['search' => ['filters' => [['field' => 'status', 'value' => 'draft']], 'limit' => 10]]);

        $response->assertOk();
        $this->assertMatchesSnapshot('action', $response);
        $this->assertSame(
            [1 => PostStatus::Published, 2 => PostStatus::Published, 3 => PostStatus::Published, 4 => PostStatus::Published],
            Post::query()
                ->orderBy('id')
                ->pluck('status', 'id')
                ->all(),
        );
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

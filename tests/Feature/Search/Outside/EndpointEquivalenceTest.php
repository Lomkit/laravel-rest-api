<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class EndpointEquivalenceTest extends PackageTestCase
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

        foreach ([[1, 'Ada', 'Lovelace', null], [2, 'Grace', 'Hopper', null], [3, 'Alan', 'Turing', null], [4, 'Edsger', 'Dijkstra', '2026-01-01 00:00:00']] as [$id, $first, $last, $deletedAt]) {
            DB::table('authors')->insert(['id' => $id, 'first_name' => $first, 'last_name' => $last, 'api_token' => "token-{$id}", 'deleted_at' => $deletedAt]);
        }

        foreach (range(1, 14) as $id) {
            DB::table('posts')->insert([
                'id' => $id,
                'author_id' => $id <= 11 ? 1 : $id % 3 + 2,
                'title' => "Post {$id}",
                'status' => $id % 3 === 0 ? 'draft' : 'published',
                'featured' => $id % 2 === 0,
                'views' => $id * 15,
                'published_on' => '2026-01-01',
            ]);
        }

        DB::table('tags')->insert([['id' => 1, 'name' => 'Mathematics', 'slug' => 'mathematics'], ['id' => 2, 'name' => 'Navy', 'slug' => 'navy']]);
        DB::table('author_tag')->insert([['author_id' => 1, 'tag_id' => 1], ['author_id' => 1, 'tag_id' => 2], ['author_id' => 2, 'tag_id' => 2], ['author_id' => 3, 'tag_id' => 1]]);
    }

    /**
     * @param  class-string<\Lomkit\Rest\Http\Resource>  $resource
     * @param  array<string, mixed>  $search
     */
    #[Test]
    #[DataProvider('searches')]
    public function it_renders_the_body_of_the_search_endpoint(string $endpoint, string $resource, array $search): void
    {
        $content = $this->postJson($endpoint, ['search' => $search])
            ->assertOk()
            ->getContent();

        $body = Rest::search($resource)
            ->as(null)
            ->payload($search)
            ->render();

        $this->assertSame($content, json_encode($body));
    }

    /** @return array<string, array{string, class-string<\Lomkit\Rest\Http\Resource>, array<string, mixed>}> */
    public static function searches(): array
    {
        $authors = '/api/authors/search';
        $posts = '/api/posts/search';
        $draft = ['field' => 'status', 'value' => 'draft'];
        $published = ['field' => 'status', 'value' => 'published'];

        return [
            'an empty search' => [$posts, PostResource::class, []],
            'nested filters' => [$posts, PostResource::class, ['filters' => [
                ['field' => 'views', 'operator' => '>', 'value' => 40],
                ['type' => 'or', 'nested' => [$draft, ['field' => 'featured', 'value' => true]]],
            ]]],
            'a relation path' => [$posts, PostResource::class, ['filters' => [['field' => 'author.first_name', 'value' => 'Ada']]]],
            'a relation group' => [$authors, AuthorResource::class, ['filters' => [['relation' => 'posts', 'exists' => false, 'nested' => [$draft]]]]],
            'sorts' => [$posts, PostResource::class, ['sorts' => [['field' => 'featured', 'direction' => 'desc'], ['field' => 'title']]]],
            'selects' => [$posts, PostResource::class, ['selects' => [['field' => 'title']], 'sorts' => [['field' => 'views']]]],
            'a scope' => [$posts, PostResource::class, ['scopes' => [['name' => 'popular']]]],
            'an instruction' => [$posts, PostResource::class, ['instructions' => [['name' => 'in-status', 'fields' => [['name' => 'status', 'value' => 'draft']]]]]],
            'nested includes' => [$authors, AuthorResource::class, ['includes' => [[
                'relation' => 'posts',
                'filters' => [$published],
                'sorts' => [['field' => 'views', 'direction' => 'desc']],
                'includes' => [['relation' => 'author']],
            ]]]],
            'an include with more rows than its limit' => [$authors, AuthorResource::class, ['includes' => [['relation' => 'posts', 'limit' => 10, 'sorts' => [['field' => 'id']]]]]],
            'a pivot include' => [$authors, AuthorResource::class, ['includes' => [['relation' => 'tags']]]],
            'aggregates' => [$authors, AuthorResource::class, ['aggregates' => [
                ['relation' => 'posts', 'type' => 'count'],
                ['relation' => 'posts', 'type' => 'max', 'field' => 'views', 'filters' => [$published]],
                ['relation' => 'posts', 'type' => 'sum', 'field' => 'views', 'alias' => 'total_views'],
            ]]],
            'trashed rows' => [$authors, AuthorResource::class, ['trashed' => 'with', 'sorts' => [['field' => 'first_name']]]],
            'gates' => [$posts, PostResource::class, ['gates' => ['view', 'update', 'delete'], 'includes' => [['relation' => 'author', 'gates' => ['view']]]]],
            'a second page' => [$posts, PostResource::class, ['limit' => 10, 'page' => 2]],
            'an unknown key' => [$posts, PostResource::class, ['unknown' => true]],
        ];
    }

    #[Test]
    public function it_renders_the_body_of_the_endpoint_without_a_payload(): void
    {
        $content = $this->postJson('/api/posts/search')
            ->assertOk()
            ->getContent();

        $body = Rest::search(PostResource::class)
            ->as(null)
            ->render();

        $this->assertSame($content, json_encode($body));
    }

    #[Test]
    public function it_reads_the_page_given_to_render_over_the_page_of_the_payload(): void
    {
        $content = $this->postJson('/api/posts/search', ['search' => ['sorts' => [['field' => 'id']], 'limit' => 10, 'page' => 2]])
            ->assertOk()
            ->assertJsonPath('data.*.id', [11, 12, 13, 14])
            ->getContent();

        $body = Rest::search(PostResource::class)
            ->as(null)
            ->payload(['sorts' => [['field' => 'id']], 'limit' => 10, 'page' => 1])
            ->render(page: 2);

        $this->assertSame($content, json_encode($body));
    }

    #[Test]
    public function it_returns_the_page_of_models_with_the_includes_cut_to_their_limit(): void
    {
        $page = Rest::search(AuthorResource::class)
            ->as(null)
            ->payload(['includes' => [['relation' => 'posts', 'limit' => 10, 'sorts' => [['field' => 'id']]]], 'sorts' => [['field' => 'first_name']]])
            ->page();

        $this->assertSame([1, 3, 2], array_map(fn (Author $author): int => $author->getKey(), $page->items()));
        $this->assertSame(
            [range(1, 10), [13], [12]],
            array_map(fn (Author $author): array => $author->getRelation('posts')->modelKeys(), $page->items()),
        );
        $this->assertSame([3, 1, 50], [$page->total(), $page->currentPage(), $page->perPage()]);
    }

    #[Test]
    public function it_returns_the_page_given_to_page(): void
    {
        $page = Rest::search(PostResource::class)
            ->as(null)
            ->payload(['sorts' => [['field' => 'id']], 'limit' => 10])
            ->page(page: 2);

        $this->assertSame([11, 12, 13, 14], array_map(fn (Post $post): int => $post->getKey(), $page->items()));
        $this->assertSame([14, 2, 10], [$page->total(), $page->currentPage(), $page->perPage()]);
    }
}

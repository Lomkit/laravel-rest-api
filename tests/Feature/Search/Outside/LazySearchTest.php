<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Exceptions\SearchRunException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class LazySearchTest extends PackageTestCase
{
    private const array BY_ID = ['sorts' => [['field' => 'id']]];

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

        foreach (range(1, 23) as $id) {
            DB::table('posts')->insert([
                'id' => $id,
                'author_id' => $id <= 11 ? 1 : $id % 3 + 2,
                'title' => "Post {$id}",
                'status' => $id % 3 === 0 ? 'draft' : 'published',
                'featured' => $id % 2 === 0,
                'views' => $id % 4 * 10,
                'rating' => $id % 3 === 0 ? null : $id % 5 + 0.5,
                'published_on' => '2026-01-01',
            ]);
        }

        DB::table('tags')->insert([['id' => 1, 'name' => 'Mathematics', 'slug' => 'mathematics'], ['id' => 2, 'name' => 'Navy', 'slug' => 'navy']]);
        DB::table('author_tag')->insert([['author_id' => 1, 'tag_id' => 1], ['author_id' => 1, 'tag_id' => 2], ['author_id' => 2, 'tag_id' => 2]]);
    }

    /**
     * @param  class-string<\Lomkit\Rest\Http\Resource>  $resource
     * @param  array<string, mixed>  $search
     */
    #[Test]
    #[DataProvider('searches')]
    public function it_reads_the_rows_of_every_page_of_the_endpoint_in_order(string $endpoint, string $resource, array $search, int $chunk): void
    {
        $rows = Rest::search($resource)
            ->as(null)
            ->payload($search)
            ->lazy($chunk);

        $this->assertSame($this->endpointRows($endpoint, $search), json_encode($rows->all()));
    }

    /** @return array<string, array{string, class-string<\Lomkit\Rest\Http\Resource>, array<string, mixed>, int}> */
    public static function searches(): array
    {
        $authors = '/api/authors/search';
        $posts = '/api/posts/search';

        return [
            'the default order' => [$posts, PostResource::class, [], 4],
            'a sort with ties' => [$posts, PostResource::class, ['sorts' => [['field' => 'views']]], 4],
            'two sorts with ties' => [$posts, PostResource::class, ['sorts' => [['field' => 'featured', 'direction' => 'desc'], ['field' => 'views', 'direction' => 'desc']]], 7],
            'a nullable sort column' => [$posts, PostResource::class, ['sorts' => [['field' => 'rating']]], 4],
            'a nullable sort column in descending order' => [$posts, PostResource::class, ['sorts' => [['field' => 'rating', 'direction' => 'desc']]], 4],
            'filters and selects' => [$posts, PostResource::class, ['filters' => [['field' => 'status', 'value' => 'published']], 'selects' => [['field' => 'title'], ['field' => 'views']]], 3],
            'an instruction' => [$posts, PostResource::class, ['instructions' => [['name' => 'in-status', 'fields' => [['name' => 'status', 'value' => 'draft']]]]], 2],
            'gates and an include' => [$posts, PostResource::class, ['gates' => ['view', 'update'], 'includes' => [['relation' => 'author', 'gates' => ['view']]]], 5],
            'includes cut to their limit' => [$authors, AuthorResource::class, ['includes' => [['relation' => 'posts', 'limit' => 10, 'sorts' => [['field' => 'id']], 'includes' => [['relation' => 'author']]], ['relation' => 'tags']]], 1],
            'aggregates' => [$authors, AuthorResource::class, ['aggregates' => [['relation' => 'posts', 'type' => 'count'], ['relation' => 'posts', 'type' => 'sum', 'field' => 'views', 'alias' => 'total_views']], 'sorts' => [['field' => 'first_name']]], 2],
            'trashed rows' => [$authors, AuthorResource::class, ['trashed' => 'with'], 3],
        ];
    }

    #[Test]
    public function it_ignores_the_limit_and_the_page_of_the_payload(): void
    {
        $rows = Rest::search(PostResource::class)
            ->as(null)
            ->payload([...self::BY_ID, 'limit' => 10, 'page' => 2])
            ->lazy();

        $this->assertSame(range(1, 23), $rows->pluck('id')->all());
    }

    /**
     * @param  class-string<\Lomkit\Rest\Http\Resource>  $resource
     * @param  array<string, mixed>  $search
     */
    #[Test]
    #[DataProvider('keyQueries')]
    public function it_reads_the_keys_without_the_columns_of_the_rows(string $endpoint, string $resource, array $search, string $select): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = str_replace(['"', '`'], '', $query->sql);
        });

        $rows = Rest::search($resource)
            ->as(null)
            ->payload($search)
            ->lazy(chunk: 4);

        $this->assertCount(1, $queries);
        $this->assertStringStartsWith($select, $queries[0]);
        $this->assertSame($this->postJson($endpoint, ['search' => $search])->json('data.*.id'), $rows->pluck('id')->all());
    }

    /** @return array<string, array{string, class-string<\Lomkit\Rest\Http\Resource>, array<string, mixed>, string}> */
    public static function keyQueries(): array
    {
        $posts = 'select posts.id from posts ';
        $authors = 'select authors.id from authors ';

        return [
            'a sort' => ['/api/posts/search', PostResource::class, ['sorts' => [['field' => 'views']], 'selects' => [['field' => 'title']]], $posts],
            'a nullable sort' => ['/api/posts/search', PostResource::class, ['sorts' => [['field' => 'rating', 'direction' => 'desc']], 'filters' => [['field' => 'featured', 'value' => true]]], $posts],
            'a relation sort' => ['/api/posts/search', PostResource::class, ['sorts' => [['field' => 'author.first_name'], ['field' => 'id']], 'filters' => [['field' => 'status', 'value' => 'draft']], 'includes' => [['relation' => 'author']]], $posts],
            'an aggregate sort' => ['/api/authors/search', AuthorResource::class, ['aggregates' => [['relation' => 'posts', 'type' => 'sum', 'field' => 'views', 'alias' => 'total_views'], ['relation' => 'posts', 'type' => 'count']], 'sorts' => [['field' => 'total_views', 'direction' => 'desc']]], $authors],
        ];
    }

    #[Test]
    public function it_reads_at_most_max_rows(): void
    {
        $search = ['sorts' => [['field' => 'views', 'direction' => 'desc']]];
        $first = $this->postJson('/api/posts/search', ['search' => [...$search, 'limit' => 10]])->json('data.*.id');

        $rows = Rest::search(PostResource::class)
            ->as(null)
            ->payload($search)
            ->lazy(chunk: 4, max: 6);
        $none = Rest::search(PostResource::class)
            ->as(null)
            ->lazy(max: 0);

        $this->assertSame(array_slice($first, 0, 6), $rows->pluck('id')->all());
        $this->assertSame([], $none->all());
    }

    #[Test]
    public function it_cuts_the_includes_of_each_chunk_to_their_limit(): void
    {
        $rows = Rest::search(AuthorResource::class)
            ->as(null)
            ->payload(['sorts' => [['field' => 'first_name']], 'includes' => [['relation' => 'posts', 'limit' => 10, ...self::BY_ID]]])
            ->lazy(chunk: 1);

        $this->assertSame([
            [1, range(1, 10), ['posts']],
            [3, [13, 16, 19, 22], []],
            [2, [12, 15, 18, 21], []],
        ], $rows->map(fn (array $row): array => [$row['id'], array_column($row['posts'], 'id'), $row['@more']])->all());
    }

    #[Test]
    public function it_skips_a_row_deleted_after_its_key_was_read(): void
    {
        $rows = Rest::search(PostResource::class)
            ->as(null)
            ->payload(self::BY_ID)
            ->lazy(chunk: 5);
        $ids = [];

        foreach ($rows as $row) {
            if ($ids === []) {
                DB::table('posts')->where('id', 7)->delete();
            }

            $ids[] = $row['id'];
        }

        $this->assertSame([...range(1, 6), ...range(8, 23)], $ids);
    }

    #[Test]
    public function it_throws_the_validation_errors_when_lazy_is_called(): void
    {
        $search = Rest::search(PostResource::class)
            ->as(null)
            ->payload(['filters' => [['field' => 'unknown', 'value' => 1]]]);

        try {
            $search->lazy();
            $this->fail('The search was not validated.');
        } catch (ValidationException $exception) {
            $this->assertSame(['search.filters.0.field'], array_keys($exception->errors()));
        }
    }

    #[Test]
    public function it_refuses_a_chunk_below_one(): void
    {
        $this->expectExceptionObject(SearchRunException::invalidChunk(0));

        Rest::search(PostResource::class)
            ->as(null)
            ->lazy(chunk: 0);
    }

    #[Test]
    public function it_refuses_a_negative_max(): void
    {
        $this->expectExceptionObject(SearchRunException::invalidMax(-1));

        Rest::search(PostResource::class)
            ->as(null)
            ->lazy(max: -1);
    }

    #[Test]
    public function it_refuses_to_read_without_a_user(): void
    {
        $this->expectExceptionObject(SearchRunException::missingUser(PostResource::class));

        Rest::search(PostResource::class)->lazy();
    }

    /** @param array<string, mixed> $search */
    private function endpointRows(string $endpoint, array $search): string
    {
        $rows = [];
        $page = 1;

        do {
            $body = json_decode((string) $this->postJson($endpoint, ['search' => [...$search, 'limit' => 10, 'page' => $page]])->assertOk()->getContent());
            $rows = [...$rows, ...$body->data];
            $page++;
        } while ($page <= $body->last_page);

        return (string) json_encode($rows);
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathLabelController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathPostController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PerimeterPathsTest extends PackageTestCase
{
    use CapturesQueries;

    /** @var list<string> */
    private array $checks = [];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('path-authors', PathAuthorController::class)->register();
        Rest::resource('path-posts', PathPostController::class)->register();
        Rest::resource('path-labels', PathLabelController::class)->register();
        Gate::before(function (?Authenticatable $user, string $ability, array $arguments): ?bool {
            $this->checks[] = $ability.' '.class_basename(is_string($arguments[0] ?? null) ? $arguments[0] : '');

            return null;
        });
        DB::table('authors')->insert([
            ['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-1'],
            ['id' => 2, 'first_name' => 'Alan', 'last_name' => 'Hidden', 'api_token' => 'token-2'],
            ['id' => 3, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'token-3'],
            ['id' => 4, 'first_name' => 'Mary', 'last_name' => 'Shelley', 'api_token' => 'token-4'],
        ]);
        DB::table('posts')->insert([
            self::postRow(1, 1, 'Notes'),
            self::postRow(2, 1, 'Hidden'),
            self::postRow(3, 2, 'Engines'),
            self::postRow(4, 3, 'Compilers'),
            self::postRow(5, 4, 'Hidden'),
        ]);
        DB::table('tags')->insert([['id' => 1, 'name' => 'Mathematics', 'slug' => 'mathematics'], ['id' => 2, 'name' => 'Hidden', 'slug' => 'hidden']]);
        DB::table('author_tag')->insert([['author_id' => 1, 'tag_id' => 1], ['author_id' => 1, 'tag_id' => 2], ['author_id' => 2, 'tag_id' => 1], ['author_id' => 3, 'tag_id' => 1]]);
        DB::table('books')->insert([['id' => 1, 'title' => 'Dune'], ['id' => 2, 'title' => 'Hidden']]);
        DB::table('shelves')->insert([['id' => 1, 'name' => 'Classics'], ['id' => 2, 'name' => 'Hidden']]);
        DB::table('book_shelf')->insert([['book_id' => 1, 'shelf_id' => 1], ['book_id' => 1, 'shelf_id' => 2], ['book_id' => 2, 'shelf_id' => 1]]);
        DB::table('labels')->insert([
            ['id' => 1, 'text' => 'classic', 'labelable_type' => Book::class, 'labelable_id' => 1],
            ['id' => 2, 'text' => 'secret', 'labelable_type' => Book::class, 'labelable_id' => 2],
        ]);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<string>  $checks
     */
    #[Test]
    #[DataProvider('paths')]
    public function it_applies_the_perimeter_of_each_level_of_a_search_path(string $uri, array $search, string $path, mixed $expected, array $checks): void
    {
        $response = $this->postJson($uri, ['search' => $search]);

        $response->assertOk()->assertJsonPath($path, $expected);
        $this->assertSame($checks, $this->checks);
    }

    /** @return array<string, array{string, array<string, mixed>, string, mixed, list<string>}> */
    public static function paths(): array
    {
        $authors = ['viewAny Author'];
        $posts = ['viewAny Author', 'viewAny Post'];
        $fromPosts = ['viewAny Post', 'viewAny Author'];
        $tags = ['viewAny Post', 'viewAny Author', 'viewAny Tag'];

        return [
            'the root' => ['/path-authors/search', [], 'data.*.id', [4, 3, 1], $authors],
            'an include' => ['/path-authors/search', ['includes' => [['relation' => 'posts']]], 'data.*.posts.*.id', [4, 1], $posts],
            'a nested include' => ['/path-authors/search', ['includes' => [['relation' => 'posts', 'includes' => [['relation' => 'author']]]]], 'data.*.posts.*.author.id', [3, 1], $posts],
            'an aliased include' => ['/path-authors/search', ['includes' => [['relation' => 'posts', 'alias' => 'writings']]], 'data.*.writings.*.id', [4, 1], $posts],
            'an owner include' => ['/path-posts/search', ['includes' => [['relation' => 'author']]], 'data.*.author.id', [3, null, 1], $fromPosts],
            'the middle of a dotted include through a has many' => ['/path-authors/search', ['includes' => [['relation' => 'posts.author']]], 'data.*.posts.*.id', [4, 1], $posts],
            'the middle of a dotted include through a belongs to' => ['/path-posts/search', ['includes' => [['relation' => 'author.tags']]], 'data.*.author.tags.*.id', [1, 1], $tags],
            'the end of a dotted include' => ['/path-posts/search', ['includes' => [['relation' => 'author.posts']]], 'data.*.author.posts.*.id', [4, 1], $fromPosts],
            'a morph to include' => ['/path-labels/search', ['includes' => [['relation' => 'labelable']]], 'data.*.labelable.id', [null, 1], ['viewAny Label', 'viewAny Book']],
            'the middle of a dotted include through a morph to' => ['/path-labels/search', ['includes' => [['relation' => 'labelable.shelves']]], 'data.*.labelable.shelves.*.id', [1], ['viewAny Label', 'viewAny Book', 'viewAny Shelf']],
            'the end of a relation filter' => ['/path-authors/search', ['filters' => [['field' => 'posts.title', 'value' => 'Hidden']]], 'data', [], $posts],
            'the middle of a relation filter through a belongs to' => ['/path-posts/search', ['filters' => [['field' => 'author.tags.name', 'value' => 'Mathematics']]], 'data.*.id', [4, 1], $tags],
            'the middle of a relation filter through a has many' => ['/path-authors/search', ['filters' => [['field' => 'posts.author.first_name', 'value' => 'Mary']]], 'data', [], $posts],
            'the middle of a relation filter through a morph to' => ['/path-labels/search', ['filters' => [['field' => 'labelable.shelves.name', 'value' => 'Classics']]], 'data.*.id', [1], ['viewAny Label', 'viewAny Book', 'viewAny Shelf']],
            'the middle of a relation filter in a group' => ['/path-posts/search', ['filters' => [['nested' => [['field' => 'author.tags.name', 'value' => 'Mathematics']]]]], 'data.*.id', [4, 1], $tags],
            'a relation group' => ['/path-posts/search', ['filters' => [['relation' => 'author', 'exists' => true]]], 'data.*.id', [4, 1], $fromPosts],
            'an aggregate' => ['/path-authors/search', ['aggregates' => [['relation' => 'posts', 'type' => 'count']]], 'data.*.posts_count', [0, 1, 1], $posts],
            'each level of a dotted aggregate' => ['/path-posts/search', ['aggregates' => [['relation' => 'author.tags', 'type' => 'count']]], 'data.*.author_tags_count', [1, 0, 1], $tags],
            'an aggregate sort' => ['/path-authors/search', ['aggregates' => [['relation' => 'posts', 'type' => 'count']], 'sorts' => [['field' => 'posts_count'], ['field' => 'first_name']]], 'data.*.id', [4, 1, 3], $posts],
            'a dotted aggregate sort' => ['/path-posts/search', ['aggregates' => [['relation' => 'author.tags', 'type' => 'count']], 'sorts' => [['field' => 'author_tags_count'], ['field' => 'id']]], 'data.*.id', [3, 1, 4], $tags],
        ];
    }

    #[Test]
    public function it_reads_the_middle_of_a_dotted_include_inside_its_perimeter_before_its_limit(): void
    {
        $this->captureQueries();

        $this->postJson('/path-authors/search', ['search' => ['includes' => [['relation' => 'posts.author']]]])->assertOk();

        $this->assertSame([[
            'select * from (select posts.id, posts.title, posts.summary, posts.status, posts.featured, posts.views, posts.rating, posts.published_on, posts.published_at, posts.metadata, posts.author_id, row_number() over (partition by posts.author_id order by posts.id desc) as laravel_row from posts where posts.author_id in (1, 3, 4) and (title != ?)) as laravel_table where laravel_row <= 1 order by laravel_row',
            ['Hidden'],
        ]], $this->readsOf('posts'));
    }

    #[Test]
    public function it_runs_an_action_on_the_rows_of_its_search_inside_the_perimeter_of_each_level(): void
    {
        $response = $this->postJson('/path-posts/actions/publish-posts', ['search' => ['filters' => [['field' => 'author.tags.name', 'value' => 'Mathematics']]]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame(['viewAny Post', 'viewAny Author', 'viewAny Tag'], $this->checks);
    }

    /** @return array<string, mixed> */
    private static function postRow(int $id, int $author, string $title): array
    {
        return ['id' => $id, 'author_id' => $author, 'title' => $title, 'status' => 'published', 'views' => $id, 'published_on' => '2026-01-01'];
    }
}

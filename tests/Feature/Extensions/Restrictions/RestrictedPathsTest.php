<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictedAuthorController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictedLabelController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictedPostController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Feature\Search\PerimeterPathsTest;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['hidden' => HiddenRowsExtension::class])]
class RestrictedPathsTest extends PackageTestCase
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

        RestrictionLog::reset();
        Rest::resource('restricted-authors', RestrictedAuthorController::class)->register();
        Rest::resource('restricted-posts', RestrictedPostController::class)->register();
        Rest::resource('restricted-labels', RestrictedLabelController::class)->register();
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

    #[\Override]
    protected function tearDown(): void
    {
        RestrictionLog::reset();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<string>  $checks
     */
    #[Test]
    #[DataProvider('paths')]
    public function it_restricts_each_level_of_a_search_path(string $uri, array $search, string $path, mixed $expected, array $checks): void
    {
        $response = $this->postJson($uri, ['search' => $search]);

        $response->assertOk()->assertJsonPath($path, $expected);
        $this->assertSame($checks, $this->checks);
    }

    /** @return array<string, array{string, array<string, mixed>, string, mixed, list<string>}> */
    public static function paths(): array
    {
        return array_map(
            static fn (array $case): array => [str_replace('/path-', '/restricted-', $case[0]), ...array_slice($case, 1)],
            PerimeterPathsTest::paths(),
        );
    }

    #[Test]
    public function it_restricts_a_relation_sort(): void
    {
        $this->captureQueries();

        $this->postJson('/restricted-posts/search', ['search' => ['sorts' => [['field' => 'author.first_name'], ['field' => 'id']]]])->assertOk();

        $this->assertSame([[
            'select posts.id, posts.title, posts.summary, posts.status, posts.featured, posts.views, posts.rating, posts.published_on, posts.published_at, posts.metadata from posts where ((posts.title != ?)) order by (select max(authors.first_name) from authors where posts.author_id = authors.id and ((authors.last_name != ?)) and authors.deleted_at is null) asc, posts.id asc limit 50 offset 0',
            ['Hidden', 'Hidden'],
        ]], $this->readsOf('posts'));
    }

    #[Test]
    public function it_restricts_the_rows_of_an_include_through_a_pivot(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/restricted-authors/search', ['search' => ['includes' => [['relation' => 'tags']]]]);

        $response->assertOk()->assertJsonPath('data.*.tags.*.id', [1, 1]);
        $this->assertSame([[
            'select * from (select tags.id, tags.name, tags.slug, tags.archived_at, tags.created_at, author_tag.author_id as pivot_author_id, author_tag.tag_id as pivot_tag_id, row_number() over (partition by author_tag.author_id order by tags.id desc) as laravel_row from tags inner join author_tag on tags.id = author_tag.tag_id where author_tag.author_id in (1, 3, 4) and ((tags.name != ?))) as laravel_table where laravel_row <= 11 order by laravel_row',
            ['Hidden'],
        ]], $this->readsOf('tags'));
    }

    #[Test]
    public function it_reads_the_middle_of_a_dotted_include_inside_its_restriction_before_its_limit(): void
    {
        $this->captureQueries();

        $this->postJson('/restricted-authors/search', ['search' => ['includes' => [['relation' => 'posts.author']]]])->assertOk();

        $this->assertSame([[
            'select * from (select posts.id, posts.title, posts.summary, posts.status, posts.featured, posts.views, posts.rating, posts.published_on, posts.published_at, posts.metadata, posts.author_id, row_number() over (partition by posts.author_id order by posts.id desc) as laravel_row from posts where posts.author_id in (1, 3, 4) and ((posts.title != ?))) as laravel_table where laravel_row <= 2 order by laravel_row',
            ['Hidden'],
        ]], $this->readsOf('posts'));
    }

    #[Test]
    public function it_runs_an_action_on_the_rows_of_its_search_inside_each_restriction(): void
    {
        $response = $this->postJson('/restricted-posts/actions/publish-posts', ['search' => ['filters' => [['field' => 'author.tags.name', 'value' => 'Mathematics']]]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame(['viewAny Post', 'viewAny Author', 'viewAny Tag'], $this->checks);
    }

    #[Test]
    public function it_gives_the_restriction_the_related_resource_and_the_user(): void
    {
        $this->actingAs(new GenericUser(['id' => 7]));

        $this->postJson('/restricted-authors/search', ['search' => [
            'filters' => [['field' => 'posts.title', 'value' => 'Notes']],
            'includes' => [['relation' => 'posts']],
        ]])->assertOk();

        $this->assertSame(['RestrictedAuthorResource 7', 'RestrictedPostResource 7', 'RestrictedPostResource 7'], RestrictionLog::$seen);
    }

    /** @return array<string, mixed> */
    private static function postRow(int $id, int $author, string $title): array
    {
        return ['id' => $id, 'author_id' => $author, 'title' => $title, 'status' => 'published', 'views' => $id, 'published_on' => '2026-01-01'];
    }
}

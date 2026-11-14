<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\UnplannedCheckException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathAuthorResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathPostController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathPostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RelationPerimeterTest extends PackageTestCase
{
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('path-authors', PathAuthorController::class)->register();
        Rest::resource('path-posts', PathPostController::class)->register();
        DB::table('authors')->insert([
            ['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-1'],
            ['id' => 2, 'first_name' => 'Alan', 'last_name' => 'Hidden', 'api_token' => 'token-2'],
        ]);
        DB::table('posts')->insert([
            ['id' => 1, 'author_id' => 1, 'title' => 'Notes', 'status' => 'published', 'views' => 1, 'published_on' => '2026-01-01'],
            ['id' => 2, 'author_id' => 2, 'title' => 'Engines', 'status' => 'published', 'views' => 1, 'published_on' => '2026-01-01'],
        ]);
    }

    #[Test]
    public function it_applies_the_perimeter_of_a_relation_in_its_own_group(): void
    {
        $context = new RestContext(RestRequest::create('/path-posts/search', 'POST'), $this->app);
        $relation = $context->resource(PathPostResource::class)->definition()->relation('author');
        $context->authorizer()->plan([Check::onClass($context->resource(PathAuthorResource::class), 'viewAny')]);
        $query = Author::query();

        $relation?->applyPerimeter($query);

        $this->assertSame(['Nested'], array_column($query->getQuery()->wheres, 'type'));
        $this->assertSame('select * from authors where (last_name != ?) and authors.deleted_at is null', $this->sql($query));
    }

    #[Test]
    public function it_still_refuses_a_relation_perimeter_that_no_check_planned(): void
    {
        $context = new RestContext(RestRequest::create('/path-posts/search', 'POST'), $this->app);
        $relation = $context->resource(PathPostResource::class)->definition()->relation('author');

        $this->expectException(UnplannedCheckException::class);

        $relation?->applyPerimeter(Author::query());
    }

    #[Test]
    public function it_keeps_one_group_for_the_perimeter_of_a_relation_sort(): void
    {
        $this->captureQueries();

        $this->postJson('/path-posts/search', ['search' => ['sorts' => [['field' => 'author.first_name'], ['field' => 'id']]]])->assertOk();

        $this->assertSame([[
            'select posts.id, posts.title, posts.summary, posts.status, posts.featured, posts.views, posts.rating, posts.published_on, posts.published_at, posts.metadata from posts where (title != ?) order by (select max(authors.first_name) from authors where posts.author_id = authors.id and (last_name != ?) and authors.deleted_at is null) asc, posts.id asc limit 50 offset 0',
            ['Hidden', 'Hidden'],
        ]], $this->readsOf('posts'));
    }

    #[Test]
    public function it_reads_a_relation_filter_through_one_group(): void
    {
        $this->captureQueries();

        $this->postJson('/path-authors/search', ['search' => ['filters' => [['field' => 'posts.title', 'value' => 'Notes'], ['relation' => 'posts', 'exists' => true]]]])->assertOk();

        $this->assertSame([[
            'select authors.id, authors.first_name, authors.last_name, authors.settings from authors where (last_name != ?) and (exists (select * from posts where authors.id = posts.author_id and posts.title = ? and (title != ?)) and exists (select * from posts where authors.id = posts.author_id and (title != ?))) and authors.deleted_at is null order by authors.id desc limit 50 offset 0',
            ['Hidden', 'Notes', 'Hidden', 'Hidden'],
        ]], $this->readsOf('authors'));
    }

    private function sql(Builder $query): string
    {
        return str_replace(['"', '`'], '', $query->toSql());
    }
}

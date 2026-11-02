<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use Illuminate\Contracts\Database\Eloquent\Builder as QueryBuilder;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Clauses\FilterGroupClause;
use Lomkit\Rest\Search\FilterCondition;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Tests\Feature\Search\Plan\ParsesSearchNodes;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilterGroupClauseTest extends PackageTestCase
{
    use ParsesSearchNodes;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_applies_the_filters_as_one_group_after_the_constraints_of_the_query(): void
    {
        $query = Post::query()->where('id', 5);

        $this->clause($this->context()->resource(PostResource::class), [
            ['field' => 'views', 'value' => 1, 'type' => 'or'],
            ['field' => 'views', 'value' => 2, 'type' => 'or'],
        ])->apply($query);

        $this->assertSame('select * from posts where id = ? and (posts.views = ? or posts.views = ?)', $this->sql($query));
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  list<string>  $titles
     */
    #[Test]
    #[DataProvider('payloads')]
    public function it_applies_a_payload_of_the_search_body(array $filters, array $titles): void
    {
        $ada = Author::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $grace = Author::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
        Post::factory()->create(['author_id' => $ada->id, 'title' => 'Annual report', 'views' => 10]);
        Post::factory()->create(['author_id' => $grace->id, 'title' => 'Spring launch', 'views' => 30]);
        Post::factory()->create(['author_id' => $grace->id, 'title' => 'Team offsite', 'views' => 5]);
        $context = $this->context();
        $context->authorizer()->plan([Check::onClass($context->resource(AuthorResource::class), 'viewAny')]);
        $query = Post::query();

        $this->clause($context->resource(PostResource::class), $filters)->apply($query);

        $this->assertSame($titles, $query
            ->orderBy('title')
            ->pluck('title')
            ->all());
    }

    /** @return array<string, array{list<array<string, mixed>>, list<string>}> */
    public static function payloads(): array
    {
        return [
            'an or' => [[
                ['field' => 'views', 'value' => 10],
                ['field' => 'views', 'operator' => '>', 'value' => 20, 'type' => 'or'],
            ], ['Annual report', 'Spring launch']],
            'a group after an or' => [[
                ['field' => 'title', 'value' => 'Annual report'],
                ['type' => 'or', 'nested' => [
                    ['field' => 'author.first_name', 'value' => 'Grace'],
                    ['field' => 'views', 'operator' => '<', 'value' => 10],
                ]],
            ], ['Annual report', 'Team offsite']],
            'a relation path' => [[['field' => 'author.first_name', 'value' => 'Grace']], ['Spring launch', 'Team offsite']],
            'a custom filter through a relation' => [[['field' => 'author.full_name', 'operator' => 'like', 'value' => 'Love%']], ['Annual report']],
            'a relation group' => [[['relation' => 'author', 'nested' => [['field' => 'first_name', 'value' => 'Grace']]]], ['Spring launch', 'Team offsite']],
        ];
    }

    #[Test]
    public function it_refuses_a_filter_that_the_field_cannot_run(): void
    {
        $resource = $this->context()->resource(PostResource::class);
        $path = FieldPath::resolve($resource, 'views');
        $this->assertNotNull($path);
        $clause = FilterGroupClause::of(new FilterGroup([new FilterCondition('views', $path, FilterOperator::Like, '1%')]));

        $this->expectExceptionObject(new InvalidFieldException('The field [views] does not support the operator [like].'));

        $clause->apply(Post::query());
    }

    /** @param list<array<string, mixed>> $filters */
    #[Test]
    #[DataProvider('relationGroupQueries')]
    public function it_compiles_a_relation_group_to_one_existence_query(array $filters, string $sql): void
    {
        $context = $this->context();
        $context->authorizer()->plan([Check::onClass($context->resource(PostResource::class), 'viewAny')]);
        $query = Author::query();

        $this->clause($context->resource(AuthorResource::class), $filters)->apply($query);

        $this->assertSame($sql, $this->sql($query));
    }

    /** @return array<string, array{list<array<string, mixed>>, string}> */
    public static function relationGroupQueries(): array
    {
        $publishedAndFeatured = [['field' => 'status', 'value' => 'published'], ['field' => 'featured', 'value' => true]];

        return [
            'one post that matches both filters' => [
                [['relation' => 'posts', 'nested' => $publishedAndFeatured]],
                'select * from authors where (exists (select * from posts where authors.id = posts.author_id and (posts.status = ? and posts.featured = ?))) and authors.deleted_at is null',
            ],
            'no post that matches' => [
                [['relation' => 'posts', 'exists' => false, 'nested' => [['field' => 'status', 'value' => 'draft']]]],
                'select * from authors where (not exists (select * from posts where authors.id = posts.author_id and (posts.status = ?))) and authors.deleted_at is null',
            ],
            'a group after an or' => [
                [['field' => 'first_name', 'value' => 'Alan'], ['type' => 'or', 'relation' => 'posts', 'nested' => $publishedAndFeatured]],
                'select * from authors where (authors.first_name = ? or exists (select * from posts where authors.id = posts.author_id and (posts.status = ? and posts.featured = ?))) and authors.deleted_at is null',
            ],
            'no post' => [
                [['relation' => 'posts', 'exists' => false]],
                'select * from authors where (not exists (select * from posts where authors.id = posts.author_id)) and authors.deleted_at is null',
            ],
        ];
    }

    /** @param list<array<string, mixed>> $filters */
    private function clause(Resource $resource, array $filters): FilterGroupClause
    {
        return FilterGroupClause::of($this->parse($resource, ['filters' => $filters])->filters);
    }

    private function context(): RestContext
    {
        return new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app);
    }

    private function sql(QueryBuilder $query): string
    {
        return str_replace(['"', '`'], '', $query->toSql());
    }
}

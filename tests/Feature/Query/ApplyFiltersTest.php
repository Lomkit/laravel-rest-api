<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use Illuminate\Contracts\Database\Eloquent\Builder as QueryBuilder;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ApplyFiltersTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_applies_the_filters_as_one_group_after_the_constraints_of_the_query(): void
    {
        $builder = new Builder($this->context()->resource(PostResource::class), Post::query()->where('id', 5));

        $builder->applyFilters([
            ['field' => 'views', 'value' => 1, 'type' => 'or'],
            ['field' => 'views', 'value' => 2, 'type' => 'or'],
        ]);

        $this->assertSame('select * from posts where id = ? and (posts.views = ? or posts.views = ?)', $this->sql($builder->toBase()));
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
        $builder = new Builder($context->resource(PostResource::class));

        $builder->applyFilters($filters);

        $this->assertSame($titles, $builder->toBase()->orderBy('title')->pluck('title')->all());
    }

    /** @return array<string, array{list<array<string, mixed>>, list<string>}> */
    public static function payloads(): array
    {
        return [
            'an upper case operator' => [[['field' => 'title', 'operator' => 'LIKE', 'value' => 'Annual%']], ['Annual report']],
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

    /** @param array<string, mixed> $filter */
    #[Test]
    #[DataProvider('refusedFilters')]
    public function it_refuses_a_filter_that_the_field_cannot_run(array $filter, string $message): void
    {
        $builder = new Builder($this->context()->resource(PostResource::class));

        $this->expectExceptionObject(new InvalidFieldException($message));

        $builder->applyFilters([$filter]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function refusedFilters(): array
    {
        return [
            'an undeclared field' => [['field' => 'nickname', 'value' => 'Ada'], 'The field [nickname] is not filterable.'],
            'a json field' => [['field' => 'metadata', 'value' => 'draft'], 'The field [metadata] is not filterable.'],
            'a write-only field of a relation' => [['field' => 'author.api_token', 'value' => 'Lovelace'], 'The field [author.api_token] is not filterable.'],
            'an operator of another type' => [['field' => 'views', 'operator' => 'like', 'value' => '1%'], 'The field [views] does not support the operator [like].'],
            'an unknown operator' => [['field' => 'title', 'operator' => 'contains', 'value' => 'report'], 'Unknown filter operator [contains].'],
            'an unknown relation' => [['relation' => 'comments'], 'Unknown relation [comments].'],
            'a relation path as a relation' => [['relation' => 'author.tags'], 'Unknown relation [author.tags].'],
            'a null relation' => [['relation' => null, 'nested' => [['field' => 'title', 'value' => 'Annual report']]], 'Unknown relation [].'],
            'a relation that is not a string' => [['relation' => ['author']], 'Unknown relation [].'],
        ];
    }

    /** @param list<array<string, mixed>> $filters */
    #[Test]
    #[DataProvider('relationGroupQueries')]
    public function it_compiles_a_relation_group_to_one_existence_query(array $filters, string $sql): void
    {
        $context = $this->context();
        $context->authorizer()->plan([Check::onClass($context->resource(PostResource::class), 'viewAny')]);
        $builder = new Builder($context->resource(AuthorResource::class));

        $builder->applyFilters($filters);

        $this->assertSame($sql, $this->sql($builder->toBase()));
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
            'exists as a number' => [
                [['relation' => 'posts', 'exists' => 0]],
                'select * from authors where (exists (select * from posts where authors.id = posts.author_id)) and authors.deleted_at is null',
            ],
        ];
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

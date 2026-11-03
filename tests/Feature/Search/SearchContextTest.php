<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Search\FlatFilter;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Feature\Search\Plan\ParsesSearchNodes;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SearchContextTest extends PackageTestCase
{
    use ParsesSearchNodes;

    private const array RELATION_GROUPS = ['filters' => [
        ['relation' => 'posts', 'exists' => false, 'nested' => [['field' => 'status', 'value' => 'draft']]],
        ['relation' => 'posts', 'nested' => [['field' => 'views', 'operator' => '>=', 'value' => 100]]],
        ['field' => 'first_name', 'value' => 'Ada'],
    ]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /**
     * @param  class-string<Resource>  $resource
     * @param  array<string, mixed>  $search
     * @param  list<array{string, string, mixed, bool}>  $filters
     */
    #[Test]
    #[DataProvider('filters')]
    public function it_flattens_the_filters_of_a_search(string $resource, array $search, array $filters): void
    {
        $this->assertSame($filters, self::flat($this->context($resource, $search)));
    }

    /** @return array<string, array{class-string<Resource>, array<string, mixed>, list<array{string, string, mixed, bool}>}> */
    public static function filters(): array
    {
        return [
            'a condition' => [PostResource::class, ['filters' => [['field' => 'views', 'operator' => '>', 'value' => 10]]], [['views', '>', 10, true]]],
            'nested groups' => [
                PostResource::class,
                ['filters' => [
                    ['field' => 'views', 'operator' => '>', 'value' => 10],
                    ['type' => 'or', 'nested' => [['field' => 'status', 'value' => 'draft'], ['field' => 'featured', 'value' => true]]],
                ]],
                [['views', '>', 10, true], ['status', '=', 'draft', true], ['featured', '=', true, true]],
            ],
            'a relation path' => [PostResource::class, ['filters' => [['field' => 'author.first_name', 'value' => 'Ada']]], [['author.first_name', '=', 'Ada', true]]],
            'relation groups' => [
                AuthorResource::class,
                self::RELATION_GROUPS,
                [['posts.status', '=', 'draft', false], ['posts.views', '>=', 100, true], ['first_name', '=', 'Ada', true]],
            ],
            'a relation group without nested' => [AuthorResource::class, ['filters' => [['relation' => 'posts', 'exists' => false]]], []],
            'a relation path in an absence group' => [
                AuthorResource::class,
                ['filters' => [['relation' => 'posts', 'exists' => false, 'nested' => [['field' => 'author.first_name', 'value' => 'Ada']]]]],
                [['posts.author.first_name', '=', 'Ada', false]],
            ],
        ];
    }

    #[Test]
    public function it_keeps_the_absence_of_every_enclosing_relation_group(): void
    {
        config(['rest.limits.depths.filters' => 2]);

        $context = $this->context(PostResource::class, ['filters' => [
            ['relation' => 'author', 'exists' => false, 'nested' => [['relation' => 'tags', 'nested' => [['field' => 'name', 'value' => 'Mathematics']]]]],
            ['relation' => 'author', 'nested' => [['relation' => 'posts', 'exists' => false, 'nested' => [['field' => 'status', 'value' => 'draft']]]]],
        ]]);

        $this->assertSame([['author.tags.name', '=', 'Mathematics', false], ['author.posts.status', '=', 'draft', false]], self::flat($context));
    }

    #[Test]
    public function it_reports_the_paths_a_search_filters_on(): void
    {
        $context = $this->context(AuthorResource::class, self::RELATION_GROUPS);

        $this->assertSame(
            [true, true, true, false, false],
            array_map($context->filtersOn(...), ['posts.status', 'posts.views', 'first_name', 'status', 'posts']),
        );
    }

    #[Test]
    public function it_reads_the_sorts_scopes_and_includes_of_a_search(): void
    {
        $context = $this->context(PostResource::class, [
            'sorts' => [['field' => 'views', 'direction' => 'desc'], ['field' => 'title']],
            'scopes' => [['name' => 'popular']],
            'includes' => [['relation' => 'author']],
        ]);

        $this->assertSame([true, true, false], array_map($context->sortsOn(...), ['views', 'title', 'status']));
        $this->assertSame(['popular'], $context->scopes());
        $this->assertSame(['author'], $context->includes());
        $this->assertSame([null, null], [$context->text, $context->trashed]);
        $this->assertFalse($context->filtersOn('views'));
    }

    #[Test]
    public function it_lists_the_includes_as_sent(): void
    {
        $context = $this->context(AuthorResource::class, [
            'includes' => [['relation' => 'posts.author'], ['relation' => 'posts', 'alias' => 'drafted']],
            'trashed' => 'with',
        ]);

        $this->assertSame(['posts.author', 'posts'], $context->includes());
        $this->assertSame(Trashed::With, $context->trashed);
    }

    /**
     * @param  class-string<Resource>  $resource
     * @param  array<string, mixed>  $search
     */
    private function context(string $resource, array $search): SearchContext
    {
        $context = new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app);

        return SearchContext::of($this->parse($context->resource($resource), $search));
    }

    /** @return list<array{string, string, mixed, bool}> */
    private static function flat(SearchContext $context): array
    {
        return array_map(
            fn (FlatFilter $filter): array => [$filter->path, $filter->operator->value, $filter->value, $filter->exists],
            $context->filters,
        );
    }
}

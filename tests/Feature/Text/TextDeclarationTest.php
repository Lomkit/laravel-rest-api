<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Feature\Text\Stubs\ContainsPostResource;
use Lomkit\Rest\Tests\Feature\Text\Stubs\MisindexedPostResource;
use Lomkit\Rest\Tests\Feature\Text\Stubs\PlainTextSearch;
use Lomkit\Rest\Tests\Feature\Text\Stubs\PlainTextSearchPostResource;
use Lomkit\Rest\Tests\Feature\Text\Stubs\StrictFullTextPostResource;
use Lomkit\Rest\Tests\Feature\Text\Stubs\UnsearchablePostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TextDeclarationTest extends TextSearchTestCase
{
    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searches')]
    public function it_refuses_a_full_text_search_without_fallback_on_a_database_without_full_text(array $search): void
    {
        if ($this->runsFullText()) {
            $this->markTestSkipped('The database runs full-text searches.');
        }

        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unsupportedDriver(StrictFullTextPostResource::class, 'sqlite'));

        $this->search('strict-full-text-posts', $search);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searches')]
    public function it_refuses_a_full_text_index_on_a_field_that_is_not_searchable(array $search): void
    {
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::notSearchable(MisindexedPostResource::class, 'status'));

        $this->search('misindexed-posts', $search);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searches')]
    public function it_refuses_a_text_search_without_searchable_field(array $search): void
    {
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::noSearchableField(UnsearchablePostResource::class));

        $this->search('unsearchable-posts', $search);
    }

    #[Test]
    public function it_refuses_a_relevance_order_from_a_strategy_that_does_not_rank(): void
    {
        $fields = FieldSet::of([Text::make('title')->searchable()]);
        $target = new TextTarget(ContainsPostResource::class, $fields, new Post, $fields);

        $this->expectExceptionObject(InvalidTextSearchException::noRelevance(ContainsPostResource::class));

        Contains::make()->orderByRelevance(Post::query(), new TextQuery('lamp'), $target, SortDirection::Desc);
    }

    #[Test]
    public function it_refuses_a_text_search_that_runs_neither_on_the_query_nor_on_an_engine(): void
    {
        $context = new RestContext(RestRequest::create('/plain-text-search-posts/search', 'POST'), $this->app);

        $this->expectExceptionObject(InvalidTextSearchException::unsupportedStrategy(PlainTextSearchPostResource::class, PlainTextSearch::class));

        $context->text($context->resource(PlainTextSearchPostResource::class));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function searches(): array
    {
        return [
            'a text' => [['text' => ['value' => 'lamp']]],
            'no text' => [[]],
        ];
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use LogicException;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Search\FilterCondition;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Search\SortNode;
use Lomkit\Rest\Tests\Feature\Search\Stubs\EarlyReadingSearchRequest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\FailureIgnoringSearchRequest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PayloadReadingSearchRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SearchPayloadTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        PayloadReadingSearchRequest::$read = null;
        $this->app->bind(SearchRequest::class, PayloadReadingSearchRequest::class);
    }

    #[Test]
    public function it_reads_the_search_as_a_typed_node(): void
    {
        Post::factory()
            ->for(Author::factory()->state(['first_name' => 'Ada', 'last_name' => 'Lovelace']))
            ->create(['id' => 1, 'views' => 20]);

        $this->postJson('/api/posts/search', ['search' => self::everyPart()])->assertOk();

        $read = $this->read();
        [$views, $group] = $read->filters->filters;
        $include = $read->includes[0];

        $this->assertSame('and', $read->filters->boolean());
        $this->assertInstanceOf(FilterCondition::class, $views);
        $this->assertInstanceOf(Integer::class, $views->field);
        $this->assertSame(['views', 'views', FilterOperator::GreaterThan, 10, 'and'], [$views->path, $views->field->name, $views->operator, $views->value, $views->boolean()]);
        $this->assertInstanceOf(FilterGroup::class, $group);
        $this->assertSame('or', $group->boolean());
        $this->assertCount(1, $group->filters);
        $this->assertInstanceOf(FilterCondition::class, $group->filters[0]);
        $this->assertInstanceOf(Text::class, $group->filters[0]->field);
        $this->assertSame(['author.first_name', 'first_name', FilterOperator::Equal, 'Ada', 'and'], [$group->filters[0]->path, $group->filters[0]->field->name, $group->filters[0]->operator, $group->filters[0]->value, $group->filters[0]->boolean()]);
        $this->assertEquals([new SortNode('views', SortDirection::Desc), new SortNode('id', null)], $read->sorts);
        $this->assertSame(['id', 'title'], $read->selects);
        $this->assertCount(1, $read->scopes);
        $this->assertSame(['popular', ['min_views' => 5]], [$read->scopes[0]->scope->name, $read->scopes[0]->parameters->toArray()]);
        $this->assertCount(1, $read->instructions);
        $this->assertSame(['highlight-posts', ['featured' => true, 'min_views' => 50]], [$read->instructions[0]->instruction->uriKey(), $read->instructions[0]->fields->toArray()]);
        $this->assertSame(['author', 'writer', 10, null, null], [$include->relation, $include->alias, $include->search->limit, $include->search->page, $include->search->text]);
        $this->assertInstanceOf(FilterCondition::class, $include->search->filters->filters[0]);
        $this->assertSame(['last_name', FilterOperator::Equal, 'Lovelace'], [$include->search->filters->filters[0]->path, $include->search->filters->filters[0]->operator, $include->search->filters->filters[0]->value]);
        $this->assertSame([['view'], 25, 1, null, null, []], [$read->gates, $read->limit, $read->page, $read->text, $read->trashed, $read->aggregates]);
    }

    #[Test]
    public function it_reads_an_aggregate_as_a_typed_node(): void
    {
        $this->postJson('/api/categories/search', ['search' => ['aggregates' => [
            ['relation' => 'products', 'type' => 'max', 'field' => 'price', 'alias' => 'top_price', 'filters' => [['field' => 'price', 'operator' => '<', 'value' => 100]]],
        ]]])->assertOk();

        $aggregate = $this->read()->aggregates[0];
        $filter = $aggregate->filters->filters[0];

        $this->assertSame(['products', AggregateFunction::Max, 'price', 'top_price'], [$aggregate->relation, $aggregate->function, $aggregate->field, $aggregate->alias]);
        $this->assertInstanceOf(FilterCondition::class, $filter);
        $this->assertSame(['price', FilterOperator::LessThan, 100], [$filter->path, $filter->operator, $filter->value]);
    }

    #[Test]
    public function it_reads_a_missing_search_as_an_empty_node(): void
    {
        $this->postJson('/api/posts/search', [])->assertOk();

        $this->assertEquals(SearchNode::empty(), $this->read());
    }

    #[Test]
    public function it_refuses_to_read_the_search_before_validation(): void
    {
        $this->expectExceptionObject(new LogicException('The search was read before the request passed validation.'));

        (new SearchRequest)->payload();
    }

    #[Test]
    public function it_refuses_to_read_the_search_while_the_request_validates(): void
    {
        $this->app->bind(SearchRequest::class, EarlyReadingSearchRequest::class);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new LogicException('The search was read before the request passed validation.'));

        $this->postJson('/api/products/search', ['search' => ['limit' => 10]]);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('failedSearches')]
    public function it_refuses_to_read_a_search_that_failed_validation(array $search): void
    {
        $this->app->bind(SearchRequest::class, FailureIgnoringSearchRequest::class);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new LogicException('The search was read before the request passed validation.'));

        $this->postJson('/api/products/search', ['search' => $search]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function failedSearches(): array
    {
        return [
            'a partial parse' => [['filters' => [['field' => 'secret']]]],
            'a recorded violation' => [['filter' => []]],
        ];
    }

    /** @return array<string, mixed> */
    private static function everyPart(): array
    {
        return [
            'filters' => [
                ['field' => 'views', 'operator' => '>', 'value' => 10],
                ['type' => 'or', 'nested' => [['field' => 'author.first_name', 'value' => 'Ada']]],
            ],
            'sorts' => [['field' => 'views', 'direction' => 'desc'], ['field' => 'id']],
            'selects' => [['field' => 'id'], ['field' => 'title']],
            'scopes' => [['name' => 'popular', 'parameters' => [5]]],
            'instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => 'min_views', 'value' => 50]]]],
            'includes' => [['relation' => 'author', 'alias' => 'writer', 'limit' => 10, 'filters' => [['field' => 'last_name', 'value' => 'Lovelace']]]],
            'gates' => ['view'],
            'limit' => 25,
            'page' => 1,
        ];
    }

    private function read(): SearchNode
    {
        $this->assertInstanceOf(SearchNode::class, PayloadReadingSearchRequest::$read);

        return PayloadReadingSearchRequest::$read;
    }
}

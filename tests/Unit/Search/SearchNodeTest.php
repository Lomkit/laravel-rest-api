<?php

namespace Lomkit\Rest\Tests\Unit\Search;

use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Search\FilterCondition;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\RelationFilterGroup;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class SearchNodeTest extends PackageTestCase
{
    #[Test]
    public function it_reads_a_missing_search_as_an_empty_node(): void
    {
        $node = SearchNode::empty();

        $this->assertEquals(new FilterGroup([], 'and'), $node->filters);
        $this->assertSame([[], [], [], [], []], [$node->filters->filters, $node->sorts, $node->aggregates, $node->includes, $node->scopes]);
        $this->assertSame([], $node->instructions);
        $this->assertSame([null, null, null, null, null, null], [$node->text, $node->trashed, $node->selects, $node->gates, $node->limit, $node->page]);
    }

    #[Test]
    public function it_joins_filters_with_and_unless_told_otherwise(): void
    {
        $path = FieldPath::resolve((new RestContext(RestRequest::create('/api/search', 'POST'), $this->app))->resource(PostResource::class), 'views');
        $this->assertInstanceOf(FieldPath::class, $path);

        $condition = new FilterCondition('views', $path, FilterOperator::GreaterThan, 10);
        $group = new FilterGroup([$condition, new FilterCondition('views', $path, FilterOperator::Equal, 3, 'or')]);

        $this->assertSame('and', $condition->boolean());
        $this->assertSame('and', $group->boolean());
        $this->assertSame('or', $group->filters[1]->boolean());
        $this->assertSame('or', (new FilterGroup([$group], 'or'))->boolean());
    }

    #[Test]
    public function it_joins_a_relation_group_with_and_by_default(): void
    {
        $group = new RelationFilterGroup(HasMany::make('posts', PostResource::class), true, new FilterGroup);

        $this->assertSame('and', $group->boolean());
    }
}

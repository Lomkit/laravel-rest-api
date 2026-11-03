<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Search\FilterCondition;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PayloadReadingSearchRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class EmptySearchPartsTest extends PackageTestCase
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
    #[DataProvider('emptyGroups')]
    public function it_reads_a_filter_whose_group_is_empty_at_the_depth_limit_as_its_condition(mixed $nested): void
    {
        $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['nested' => [['field' => 'views', 'value' => 1, 'nested' => $nested]]],
        ]]])->assertOk();

        $group = $this->read()->filters->filters[0];

        $this->assertInstanceOf(FilterGroup::class, $group);
        $this->assertCount(1, $group->filters);
        $this->assertInstanceOf(FilterCondition::class, $group->filters[0]);
        $this->assertSame(['views', FilterOperator::Equal, 1], [$group->filters[0]->name, $group->filters[0]->operator, $group->filters[0]->value]);
    }

    /** @return array<string, array{mixed}> */
    public static function emptyGroups(): array
    {
        return [
            'a null group' => [null],
        ];
    }

    #[Test]
    public function it_reads_an_empty_list_at_the_depth_limit_as_an_empty_group(): void
    {
        $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['nested' => [['field' => 'views', 'value' => 1, 'type' => 'or', 'nested' => []]]],
        ]]])->assertOk();

        $group = $this->read()->filters->filters[0];

        $this->assertInstanceOf(FilterGroup::class, $group);
        $this->assertEquals([new FilterGroup([], 'or')], $group->filters);
    }

    #[Test]
    #[DataProvider('emptyFields')]
    public function it_reads_an_empty_field_of_a_count_as_no_field(mixed $field): void
    {
        $this->postJson('/api/categories/search', ['search' => ['aggregates' => [
            ['relation' => 'products', 'type' => 'count', 'field' => $field],
        ]]])->assertOk();

        $aggregate = $this->read()->aggregates[0];

        $this->assertSame(['products', AggregateFunction::Count, null, null, []], [$aggregate->relation, $aggregate->function, $aggregate->field, $aggregate->alias, $aggregate->filters->filters]);
    }

    /** @return array<string, array{mixed}> */
    public static function emptyFields(): array
    {
        return [
            'an empty list' => [[]],
        ];
    }

    private function read(): SearchNode
    {
        $this->assertInstanceOf(SearchNode::class, PayloadReadingSearchRequest::$read);

        return PayloadReadingSearchRequest::$read;
    }
}

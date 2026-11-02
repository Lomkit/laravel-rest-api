<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Search\FlatFilter;
use Lomkit\Rest\Search\RelevanceSort;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Search\SortNode;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PayloadReadingSearchRequest;
use Lomkit\Rest\Text\Engine\EngineFilterCondition;
use Lomkit\Rest\Text\Engine\EngineSort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EnginePayloadTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        PayloadReadingSearchRequest::$read = null;
        $this->app->bind(SearchRequest::class, PayloadReadingSearchRequest::class);
        config(['scout.driver' => 'collection']);
    }

    #[Test]
    #[DataProvider('emptyGroups')]
    public function it_reads_an_engine_filter_whose_group_is_empty_as_its_condition(mixed $nested): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'number', 'value' => 3, 'nested' => $nested]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1]);

        $filters = PayloadReadingSearchRequest::$read?->filters->filters;

        $this->assertIsArray($filters);
        $this->assertCount(1, $filters);
        $this->assertInstanceOf(EngineFilterCondition::class, $filters[0]);
        $this->assertSame(['number', 'number', FilterOperator::Equal, 3], [$filters[0]->name, $filters[0]->attribute->field->name, $filters[0]->operator, $filters[0]->value]);
    }

    /** @return array<string, array{mixed}> */
    public static function emptyGroups(): array
    {
        return [
            'a null group' => [null],
            'an empty group' => [[]],
        ];
    }

    /**
     * @param  list<array<string, string>>  $sorts
     * @param  list<mixed>  $read
     */
    #[Test]
    #[DataProvider('engineSorts')]
    public function it_reads_an_engine_sort_per_kind(array $sorts, array $read): void
    {
        $this->search('vouchers', ['text' => ['value' => 'report'], 'sorts' => $sorts])->assertOk();

        $this->assertSame($read, array_map(
            fn (SortNode $sort): array => $sort instanceof EngineSort ? [$sort::class, $sort->attribute, $sort->direction()] : [$sort::class, $sort->direction()],
            PayloadReadingSearchRequest::$read->sorts ?? [],
        ));
    }

    /** @return array<string, array{list<array<string, string>>, list<mixed>}> */
    public static function engineSorts(): array
    {
        return [
            'an index attribute' => [[['field' => 'price']], [[EngineSort::class, 'price', SortDirection::Asc]]],
            'the relevance order' => [[['field' => '@relevance']], [[RelevanceSort::class, SortDirection::Desc]]],
        ];
    }

    /**
     * @param  list<array<string, string>>  $sorts
     * @param  list<bool>  $sorted
     */
    #[Test]
    #[DataProvider('contextSorts')]
    public function it_builds_the_search_context_of_an_engine_search(array $sorts, array $sorted): void
    {
        $response = $this->search('vouchers', [
            'text' => ['value' => 'report'],
            'filters' => [['field' => 'number', 'operator' => '>=', 'value' => 3]],
            'sorts' => $sorts,
        ]);

        $response->assertOk();
        $this->assertNotNull(PayloadReadingSearchRequest::$read);

        $context = SearchContext::of(PayloadReadingSearchRequest::$read);

        $this->assertSame([['number', '>=', 3, true]], array_map(
            fn (FlatFilter $filter): array => [$filter->path, $filter->operator->value, $filter->value, $filter->exists],
            $context->filters,
        ));
        $this->assertSame($sorted, [$context->sortsOn('price'), $context->sortsOn('@relevance')]);
        $this->assertSame('report', $context->text?->value);
    }

    /** @return array<string, array{list<array<string, string>>, list<bool>}> */
    public static function contextSorts(): array
    {
        return [
            'an index attribute' => [[['field' => 'price', 'direction' => 'desc']], [true, false]],
            'the relevance order' => [[['field' => '@relevance']], [false, true]],
        ];
    }
}

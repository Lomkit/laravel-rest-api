<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Search\FilterCondition;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PayloadReadingSearchRequest;
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
        $this->withoutMiddleware(ConvertEmptyStringsToNull::class);
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
        $this->assertInstanceOf(FilterCondition::class, $filters[0]);
        $this->assertSame(['number', FilterOperator::Equal, 3], [$filters[0]->path, $filters[0]->operator, $filters[0]->value]);
    }

    /** @return array<string, array{mixed}> */
    public static function emptyGroups(): array
    {
        return [
            'a null group' => [null],
            'an empty group' => [[]],
        ];
    }
}

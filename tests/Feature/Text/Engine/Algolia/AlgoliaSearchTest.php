<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Algolia;

use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('algolia')]
class AlgoliaSearchTest extends AlgoliaTestCase
{
    #[Test]
    public function it_sends_the_text_the_filters_and_a_zero_based_page_and_reads_the_object_ids_and_the_hit_count(): void
    {
        $this->hits(['V-a'], 3, 1, 2);

        $response = $this->search('unsorted-vouchers', [
            'text' => ['value' => 'report'],
            'filters' => [['field' => 'price', 'operator' => '<', 'value' => 40], ['field' => 'number', 'operator' => 'in', 'value' => [1, 3, 4, 5]]],
            'sorts' => [['field' => '@relevance']],
            'limit' => 2,
            'page' => 2,
        ]);

        $response->assertOk()->assertExactJson($this->page([3], 3, 2, 2));
        $this->assertSame([['POST', "/1/indexes/{$this->index}/query", [
            'query' => 'report',
            'filters' => "__soft_deleted:'0' AND price<40 AND (number:'1' OR number:'3' OR number:'4' OR number:'5')",
            'hitsPerPage' => 2,
            'page' => 1,
        ]]], $this->algolia->sent('POST', "/1/indexes/{$this->index}/query"));
    }

    #[Test]
    public function it_drops_the_hits_outside_the_database_perimeter_from_the_page(): void
    {
        $this->hits(['V-e', 'V-d', 'V-c', 'V-a', 'V-f'], 5);

        $response = $this->search('unsorted-vouchers', ['text' => ['value' => 'report'], 'trashed' => 'with']);

        $response->assertOk()->assertExactJson($this->page([5, 1, 3, 6], 5));
        $this->assertSame([['POST', "/1/indexes/{$this->index}/query", ['query' => 'report', 'filters' => '', 'hitsPerPage' => 50, 'page' => 0]]], $this->algolia->sent('POST', "/1/indexes/{$this->index}/query"));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('reaches')]
    public function it_refuses_an_action_whose_text_search_may_reach_past_the_pagination_limit_of_the_index(int $status, array $settings, ?string $refusal, array $ids): void
    {
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->hits(['V-e', 'V-c', 'V-a'], 3, 0, 100);
        $this->algolia->respond('GET', "/1/indexes/{$this->index}/settings", $status, $settings);

        $response = $this->postJson('/api/unsorted-vouchers/actions/review-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        if ($refusal === null) {
            $response->assertOk()->assertExactJson(['data' => ['impacted' => count($ids)]]);
        } else {
            $response->assertUnprocessable()->assertExactJson(['message' => $refusal, 'errors' => ['search' => [$refusal]]]);
        }

        $this->assertSame($ids === [] ? [] : [['handle', ['ids' => $ids]]], $received->calls());
        $this->assertSame([['POST', "/1/indexes/{$this->index}/query", ['query' => 'report', 'filters' => "__soft_deleted:'0'", 'hitsPerPage' => 100, 'page' => 0]]], $this->algolia->sent('POST', "/1/indexes/{$this->index}/query"));
        $this->assertCount(1, $this->algolia->sent('GET', "/1/indexes/{$this->index}/settings"));
    }

    /** @return array<string, array{int, array<string, mixed>, ?string, list<int>}> */
    public static function reaches(): array
    {
        return [
            'a hit count at the pagination limit' => [200, ['paginationLimitedTo' => 3, 'hitsPerPage' => 20], 'The search engine returns at most 3 results for this search; narrow the search.', []],
            'a hit count under the pagination limit' => [200, ['paginationLimitedTo' => 1000, 'hitsPerPage' => 20], null, [5, 1, 3]],
            'settings the key cannot read' => [403, ['message' => 'Method not allowed with this API key', 'status' => 403], 'The search engine does not report how many results this search can reach; give the search a limit.', []],
        ];
    }
}

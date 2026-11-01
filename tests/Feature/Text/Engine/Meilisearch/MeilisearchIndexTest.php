<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Meilisearch;

use Illuminate\Auth\GenericUser;
use Laravel\Scout\EngineManager;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Meilisearch\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('meilisearch')]
class MeilisearchIndexTest extends MeilisearchTestCase
{
    private const array BY_PRICE = [['field' => 'price', 'direction' => 'asc']];

    private const array ACTIVE = ['text' => ['value' => 'report'], 'filters' => [['field' => 'active', 'value' => true]], 'sorts' => self::BY_PRICE];

    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));
    }

    #[Test]
    public function it_drops_the_stale_rows_of_the_index_from_the_page_and_keeps_the_engine_total(): void
    {
        $this->actingAs(new GenericUser(['id' => 2, 'teams' => ['red']]));
        Voucher::withoutSyncingToSearch(function (): void {
            Voucher::query()
                ->findOrFail(1)
                ->delete();
            Voucher::query()
                ->findOrFail(3)
                ->update(['team' => 'blue']);
        });

        $response = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE]);

        $response->assertOk()->assertExactJson($this->page([5], 3));
    }

    #[Test]
    public function it_runs_an_action_on_the_rows_the_engine_matches_and_indexes_its_changes(): void
    {
        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE]]);
        $this->settle();
        $inactive = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'active', 'value' => false]], 'sorts' => self::BY_PRICE]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3]]);
        $this->assertSame([['handle', ['ids' => [1, 3, 5]]]], $this->received->calls());
        $inactive->assertOk()
            ->assertJsonPath('data.*.id', [1, 3, 5])
            ->assertJsonPath('data.*.active', [false, false, false])
            ->assertJsonPath('total', 3);
    }

    #[Test]
    public function it_reads_every_key_before_the_action_changes_the_indexed_rows(): void
    {
        $response = $this->postJson('/api/team-vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report'], 'trashed' => 'with', 'sorts' => self::BY_PRICE]]);
        $this->settle();
        $report = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'trashed' => 'with']);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 5]]);
        $this->assertSame([['handle', ['ids' => [1, 6]]], ['handle', ['ids' => [3, 4]]], ['handle', ['ids' => [5]]]], $this->received->calls());
        $report->assertOk()->assertExactJson($this->page([], 0));
    }

    /** @param list<int> $ids */
    #[Test]
    #[DataProvider('reaches')]
    public function it_refuses_an_action_whose_text_search_may_reach_past_the_pagination_limit_of_the_index(int $maxTotalHits, ?string $refusal, array $ids): void
    {
        $this->indexes->configure($this->index, ['pagination' => ['maxTotalHits' => $maxTotalHits]]);

        $response = $this->postJson('/api/team-vouchers/actions/review-vouchers', ['search' => self::ACTIVE]);
        $this->settle();

        if ($refusal === null) {
            $response->assertOk()->assertExactJson(['data' => ['impacted' => count($ids)]]);
        } else {
            $response->assertUnprocessable()->assertExactJson(['message' => $refusal, 'errors' => ['search' => [$refusal]]]);
        }

        $this->assertSame($ids === [] ? [] : [['handle', ['ids' => $ids]]], $this->received->calls());
    }

    /** @return array<string, array{int, ?string, list<int>}> */
    public static function reaches(): array
    {
        return [
            'a total at the limit' => [3, 'The search engine returns at most 3 results for this search; narrow the search.', []],
            'a total under the limit' => [4, null, [1, 3, 4]],
        ];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('unreadableLimits')]
    public function it_refuses_an_action_when_the_key_cannot_read_the_pagination_limit_of_the_index(array $search, ?string $refusal, array $ids): void
    {
        $key = $this->indexes->key($this->index, ['search', 'documents.add']);
        $this->beforeApplicationDestroyed(fn () => $this->indexes->forget($key));
        config(['scout.meilisearch.key' => $key]);
        $this->app->forgetInstance(Client::class);
        $this->app->make(EngineManager::class)->forgetEngines();

        $response = $this->postJson('/api/team-vouchers/actions/review-vouchers', ['search' => [...self::ACTIVE, ...$search]]);
        $this->settle();

        if ($refusal === null) {
            $response->assertOk()->assertExactJson(['data' => ['impacted' => count($ids)]]);
        } else {
            $response->assertUnprocessable()->assertExactJson(['message' => $refusal, 'errors' => ['search' => [$refusal]]]);
        }

        $this->assertSame($ids === [] ? [] : [['handle', ['ids' => $ids]]], $this->received->calls());
    }

    /** @return array<string, array{array<string, mixed>, ?string, list<int>}> */
    public static function unreadableLimits(): array
    {
        return [
            'without a search limit' => [[], 'The search engine does not report how many results this search can reach; give the search a limit.', []],
            'with a search limit' => [['limit' => 10], null, [1, 3, 4]],
        ];
    }
}

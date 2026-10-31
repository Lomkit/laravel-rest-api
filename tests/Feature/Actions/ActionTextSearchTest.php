<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Actions\CallRestApiAction;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ActionTextSearchTest extends EngineSearchTestCase
{
    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        config(['scout.soft_delete' => true]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('engineMatches')]
    public function it_acts_on_the_rows_the_engine_matches_inside_the_database_perimeter(string $driver, array $search, array $ids): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report'], ...$search]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => count($ids)]]);
        $this->assertSame([['handle', ['ids' => $ids]]], $this->received->calls());
        $this->assertSame(array_values(array_diff([4, 6], $ids)), $this->activeAmong([4, 6, ...$ids]));
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<int>}> */
    public static function engineMatches(): iterable
    {
        foreach (self::drivers() as $engine => [$driver]) {
            yield "the indexed rows on {$engine}" => [$driver, [], [5, 1, 3]];
            yield "the indexed and trashed rows on {$engine}" => [$driver, ['trashed' => 'with'], [6, 5, 1, 3]];
        }
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_reads_every_key_before_the_action_changes_the_indexed_rows(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->postJson('/api/team-vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report'], 'trashed' => 'with']]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 5]]);
        $this->assertSame([['handle', ['ids' => [6, 5]]], ['handle', ['ids' => [4, 1]]], ['handle', ['ids' => [3]]]], $this->received->calls());
        $this->assertSame(['Renamed V-c', 'Budget', 'Renamed V-a', 'Renamed V-d', 'Renamed V-e', 'Renamed V-f'], $this->titles());
    }

    #[Test]
    public function it_reads_the_keys_in_pages_of_the_chunk_count(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $this->postJson('/api/team-vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report'], 'trashed' => 'with']])->assertOk();

        $this->assertSame([[2, 1], [2, 2], [2, 3]], $engine->pages);
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  array<string, mixed>  $json
     * @param  list<array{string, array<string, mixed>}>  $calls
     */
    #[Test]
    #[DataProvider('caps')]
    public function it_runs_an_action_on_at_most_its_maximum_of_resources(array $filters, int $status, array $json, array $calls): void
    {
        $response = $this->postJson('/api/team-vouchers/actions/review-vouchers', ['search' => ['text' => ['value' => 'report'], 'filters' => $filters]]);

        $response->assertStatus($status)->assertExactJson($json);
        $this->assertSame($calls, $this->received->calls());
    }

    /** @return array<string, array{list<array<string, mixed>>, int, array<string, mixed>, list<array{string, array<string, mixed>}>}> */
    public static function caps(): array
    {
        $message = 'This action can be run on at most 3 resources; narrow the search.';

        return [
            'three matches' => [[['field' => 'active', 'value' => true]], 200, ['data' => ['impacted' => 3]], [['handle', ['ids' => [4, 1, 3]]]]],
            'four matches' => [[], 422, ['message' => $message, 'errors' => ['search' => [$message]]], []],
        ];
    }

    #[Test]
    #[DataProvider('queuedActions')]
    public function it_queues_nothing_for_an_action_whose_text_search_matches_too_many_resources(string $action): void
    {
        Queue::fake();
        Bus::fake();

        $response = $this->postJson("/api/team-vouchers/actions/{$action}", ['search' => ['text' => ['value' => 'report']]]);

        $response->assertUnprocessable()->assertJsonPath('errors.search', ['This action can be run on at most 3 resources; narrow the search.']);
        Queue::assertNothingPushed();
        Bus::assertNothingDispatched();
        Bus::assertNothingBatched();
        $this->assertSame([], $this->received->calls());
    }

    /** @return array<string, array{string}> */
    public static function queuedActions(): array
    {
        return [
            'a queued action' => ['queued-review-vouchers'],
            'a batched action' => ['batched-review-vouchers'],
        ];
    }

    #[Test]
    public function it_queues_the_rows_of_an_action_whose_text_search_stays_under_its_maximum(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/team-vouchers/actions/queued-review-vouchers', ['search' => ['text' => ['value' => 'report'], 'filters' => [['field' => 'active', 'value' => true]]]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3]]);
        Queue::assertPushed(CallRestApiAction::class, 1);
        Queue::assertPushed(CallRestApiAction::class, fn (CallRestApiAction $job): bool => (fn (): array => $this->models->pluck('id')->all())->call($job) === [4, 1, 3]);
        $this->assertSame([], $this->received->calls());
    }

    #[Test]
    public function it_acts_on_the_first_rows_of_a_limited_text_search_that_matches_more_than_the_maximum(): void
    {
        $response = $this->postJson('/api/team-vouchers/actions/review-vouchers', ['search' => ['text' => ['value' => 'report'], 'limit' => 2]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertSame([['handle', ['ids' => [5, 4]]]], $this->received->calls());
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function activeAmong(array $ids): array
    {
        return Voucher::withTrashed()
            ->whereIn('id', $ids)
            ->where('active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /** @return list<string> */
    private function titles(): array
    {
        return Voucher::withTrashed()
            ->orderBy('id')
            ->pluck('title')
            ->all();
    }
}

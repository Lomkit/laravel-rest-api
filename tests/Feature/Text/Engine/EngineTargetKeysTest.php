<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Actions\EngineTargetKeys;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PagedMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PagedTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ShiftingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

#[Group('scout')]
class EngineTargetKeysTest extends EngineSearchTestCase
{
    private const array ACTIVE = ['text' => ['value' => 'report'], 'filters' => [['field' => 'active', 'value' => true]]];

    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));
        Route::prefix('api')->group(fn () => Rest::resource('unsorted-vouchers', UnsortedVoucherController::class)->register());
    }

    #[Test]
    #[DataProvider('shifts')]
    public function it_refuses_an_action_whose_engine_pages_change_while_its_keys_are_read(bool $repeats): void
    {
        config(['scout.soft_delete' => true]);
        $this->useEngine(new ShiftingCollectionEngine($repeats));

        $response = $this->postJson('/api/team-vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report'], 'trashed' => 'with']]);

        $this->assertRefused($response->json(), 'The search results changed while the action was resolving them; run the action again.');
        $this->assertSame([], $this->received->calls());
        $this->assertSame(0, Voucher::withTrashed()
            ->where('title', 'like', 'Renamed%')
            ->count());
    }

    /** @return array<string, array{bool}> */
    public static function shifts(): array
    {
        return [
            'a second page repeating a key' => [true],
            'a second page missing a key' => [false],
        ];
    }

    /**
     * @param  Closure(): mixed  $settings
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('reaches')]
    public function it_refuses_an_action_whose_text_search_may_reach_past_the_engine_limit(Closure $settings, array $search, ?string $refusal, array $ids): void
    {
        $this->useEngine(new PagedMeilisearchEngine(new SettingsClient($settings)));

        $response = $this->postJson('/api/team-vouchers/actions/review-vouchers', ['search' => [...self::ACTIVE, ...$search]]);

        if ($refusal === null) {
            $response->assertOk()->assertExactJson(['data' => ['impacted' => count($ids)]]);
        } else {
            $this->assertRefused($response->json(), $refusal);
        }

        $this->assertSame($ids === [] ? [] : [['handle', ['ids' => $ids]]], $this->received->calls());
    }

    /** @return array<string, array{Closure(): mixed, array<string, mixed>, ?string, list<int>}> */
    public static function reaches(): array
    {
        $unreadable = fn (): never => throw new RuntimeException('The key cannot read the settings.');

        return [
            'a total at the engine limit' => [fn (): array => ['maxTotalHits' => 3], [], 'The search engine returns at most 3 results for this search; narrow the search.', []],
            'a total under the engine limit' => [fn (): array => ['maxTotalHits' => 4], [], null, [4, 1, 3]],
            'an engine limit above the maximum' => [fn (): array => ['maxTotalHits' => 1000], [], null, [4, 1, 3]],
            'an unreadable engine limit' => [$unreadable, [], 'The search engine does not report how many results this search can reach; give the search a limit.', []],
            'an unreadable engine limit with a search limit' => [$unreadable, ['limit' => 10], null, [4, 1, 3]],
        ];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<list<int>>  $chunks
     * @param  list<array{int, int}>  $pages
     */
    #[Test]
    #[DataProvider('uncounted')]
    public function it_reads_the_keys_of_an_engine_without_exact_total_until_a_short_page(string $action, array $search, ?string $refusal, array $chunks, array $pages): void
    {
        $engine = new PagedTurbopufferEngine;
        $this->useEngine($engine);

        $response = $this->postJson("/api/unsorted-vouchers/actions/{$action}", ['search' => ['text' => ['value' => 'report'], ...$search]]);

        if ($refusal === null) {
            $response->assertOk()->assertExactJson(['data' => ['impacted' => count(array_merge(...$chunks))]]);
        } else {
            $this->assertRefused($response->json(), $refusal);
        }

        $this->assertSame(array_map(fn (array $ids): array => ['handle', ['ids' => $ids]], $chunks), $this->received->calls());
        $this->assertSame($pages, $engine->pages);
    }

    /** @return array<string, array{string, array<string, mixed>, ?string, list<list<int>>, list<array{int, int}>}> */
    public static function uncounted(): array
    {
        return [
            'pages of the chunk count' => ['retitle-vouchers', [], null, [[5], [1, 3]], [[2, 1], [2, 2], [2, 3]]],
            'more keys than the maximum' => ['review-vouchers', [], 'This action can be run on at most 3 resources; narrow the search.', [], [[100, 1]]],
            'a limit under the maximum' => ['review-vouchers', ['limit' => 2], null, [[5]], [[100, 1]]],
        ];
    }

    #[Test]
    #[DataProvider('chunkCounts')]
    public function it_refuses_to_read_keys_past_the_engine_window(int $chunkCount): void
    {
        $this->useEngine(new PagedTurbopufferEngine(endless: true));
        $resource = $this->resource(UnsortedVoucherResource::class, $this->context(['search' => ['text' => ['value' => 'report']]]));
        $text = $resource->context()->text($resource);
        $query = $text->engineQuery($resource->context()->request);
        $this->assertNotNull($query);

        try {
            (new EngineTargetKeys($query, $chunkCount, 20000))->resolve();
            $this->fail('The keys past the engine window were read.');
        } catch (ValidationException $exception) {
            $this->assertSame(['search' => ['The search engine returns at most 10000 results for this search; narrow the search.']], $exception->errors());
        }
    }

    /** @return array<string, array{int}> */
    public static function chunkCounts(): array
    {
        return [
            'chunks inside the window' => [5000],
            'chunks larger than the window' => [20000],
        ];
    }

    private function assertRefused(mixed $json, string $message): void
    {
        $this->assertSame(['message' => $message, 'errors' => ['search' => [$message]]], $json);
    }
}

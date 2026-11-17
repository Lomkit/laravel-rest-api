<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\CapturingMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TiedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TiedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineTieBreakerTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('tied-vouchers', TiedVoucherController::class)->register());
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_orders_a_list_sent_without_a_sort_on_the_tie_breaker_descending(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('tied-vouchers', []);

        $response->assertOk()->assertExactJson($this->page([5, 4, 3, 1, 2], 5));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_breaks_the_ties_of_the_requested_sorts_on_the_tie_breaker(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('tied-vouchers', ['sorts' => [['field' => 'team']]]);

        $response->assertOk()->assertExactJson($this->page([4, 5, 3, 1, 2], 5));
    }

    #[Test]
    public function it_sends_the_tie_breaker_to_the_engine_after_the_requested_sorts(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $this->search('tied-vouchers', [])->assertOk();
        $this->search('tied-vouchers', ['sorts' => [['field' => 'team'], ['field' => 'price', 'direction' => 'desc']]])->assertOk();
        $this->search('tied-vouchers', ['sorts' => [['field' => 'title'], ['field' => 'team']]])->assertOk();
        $this->search('tied-vouchers', ['text' => ['value' => 'report'], 'sorts' => [['field' => 'team']]])->assertOk();

        $this->assertSame([
            [['column' => 'title', 'direction' => 'desc']],
            [['column' => 'team', 'direction' => 'asc'], ['column' => 'price', 'direction' => 'desc'], ['column' => 'title', 'direction' => 'desc']],
            [['column' => 'title', 'direction' => 'asc'], ['column' => 'team', 'direction' => 'asc']],
            [['column' => 'team', 'direction' => 'asc']],
        ], array_column($engine->searches, 'orders'));
    }

    #[Test]
    public function it_sends_a_string_tie_breaker_to_typesense_in_the_sort_it_reserves(): void
    {
        $engine = $this->useTypesense();

        $this->search('tied-vouchers', [])->assertOk();
        $this->search('tied-vouchers', ['sorts' => [['field' => 'price', 'direction' => 'desc'], ['field' => 'number']]])->assertOk();

        $this->assertSame(['title:desc', 'price:desc,number:asc,title:desc'], array_column($engine->parameters, 'sort_by'));
    }

    #[Test]
    public function it_refuses_a_third_sort_on_typesense_when_the_tie_breaker_takes_one(): void
    {
        $engine = $this->useTypesense();

        $response = $this->search('tied-vouchers', ['sorts' => [['field' => 'price'], ['field' => 'number'], ['field' => 'team']]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.sorts' => ['The search.sorts field must not have more than 2 items.']]);
        $this->assertSame([], $engine->parameters);
    }

    #[Test]
    public function it_sends_the_tie_breaker_to_meilisearch_after_the_requested_sorts(): void
    {
        $engine = new CapturingMeilisearchEngine(new SettingsClient(fn (): array => ['maxTotalHits' => 1000]));
        $this->useEngine($engine);

        $response = $this->search('tied-vouchers', ['sorts' => [['field' => 'team']]]);

        $response->assertOk()->assertExactJson($this->page([4, 5, 3, 1, 2], 5));
        $this->assertSame([['q' => '', 'filter' => '', 'sort' => ['team:asc', 'title:desc']]], $engine->requests);
    }

    #[Test]
    public function it_refuses_the_tie_breaker_at_the_first_search_on_an_engine_that_takes_no_sort_without_text(): void
    {
        $this->useEngine(new FakeAlgolia4Engine(new SettingsClient(fn (): array => [])));
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unsortedTieBreaker(TiedVoucherResource::class, 'title', FakeAlgolia4Engine::class));

        $this->search('tied-vouchers', []);
    }

    #[Test]
    public function it_sends_no_tie_breaker_to_typesense_behind_a_sort_that_holds_it_or_behind_a_text(): void
    {
        $engine = $this->useTypesense();

        $this->search('tied-vouchers', ['sorts' => [['field' => 'price'], ['field' => 'title', 'direction' => 'desc']]])->assertOk();
        $this->search('tied-vouchers', ['text' => ['value' => 'report'], 'sorts' => [['field' => 'price'], ['field' => 'number'], ['field' => 'team']]])->assertOk();

        $this->assertSame(['price:asc,title:desc', 'price:asc,number:asc,team:asc'], array_column($engine->parameters, 'sort_by'));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_pages_the_ties_of_a_requested_sort_without_overlap(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $pages = array_map(fn (int $page): array => $this->search('tied-vouchers', ['sorts' => [['field' => 'team']], 'limit' => 2, 'page' => $page])->assertOk()->json('data.*.id'), [1, 2, 3]);

        $this->assertSame([[4, 5], [3, 1], [2]], $pages);
    }

    private function useTypesense(): FilterCapturingTypesenseEngine
    {
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);

        return $engine;
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class TermlessEngineTextTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        Route::prefix('api')->group(fn () => Rest::resource('listed-vouchers', ListedVoucherController::class)->withSoftDeletes());
    }

    #[Test]
    #[DataProvider('termlessTexts')]
    public function it_matches_nothing_for_a_text_without_terms(string $driver, string $value): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('vouchers', ['text' => ['value' => $value]]);

        $response->assertOk()->assertExactJson($this->sqlPage([], 0));
    }

    #[Test]
    #[DataProvider('termlessTexts')]
    public function it_matches_nothing_for_a_text_without_terms_in_an_engine_list(string $driver, string $value): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('listed-vouchers', ['source' => 'engine', 'text' => ['value' => $value]]);

        $response->assertOk()->assertExactJson($this->page([], 0));
    }

    /** @return iterable<string, array{string, string}> */
    public static function termlessTexts(): iterable
    {
        foreach (self::drivers() as $engine => [$driver]) {
            foreach (['*', '%', '_', '-'] as $value) {
                yield "{$value} on {$engine}" => [$driver, $value];
            }
        }
    }

    #[Test]
    public function it_sends_no_search_to_the_engine_for_a_text_without_terms(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $listed = $this->search('listed-vouchers', ['source' => 'engine', 'text' => ['value' => '*'], 'filters' => [['field' => 'price', 'operator' => '<', 'value' => 40]], 'sorts' => [['field' => 'price']]]);
        $searched = $this->search('vouchers', ['text' => ['value' => '*']]);

        $listed->assertOk()->assertExactJson($this->page([], 0));
        $searched->assertOk()->assertExactJson($this->sqlPage([], 0));
        $this->assertSame([], $engine->searches);
    }

    #[Test]
    #[DataProvider('blankValues')]
    public function it_returns_the_normal_list_for_a_blank_text(?string $value): void
    {
        config(['scout.driver' => 'collection']);

        $listed = $this->search('listed-vouchers', ['source' => 'engine', 'text' => ['value' => $value]]);
        $searched = $this->search('vouchers', ['text' => ['value' => $value]]);

        $listed->assertOk()->assertExactJson($this->page([5, 4, 1, 2, 3], 5));
        $searched->assertOk()->assertExactJson($this->sqlPage([5, 3, 2, 1], 4));
    }

    /** @return array<string, array{?string}> */
    public static function blankValues(): array
    {
        return [
            'an empty value' => [''],
            'spaces' => ["  \t "],
            'a null value' => [null],
        ];
    }

    #[Test]
    public function it_pages_renders_and_streams_nothing_for_a_programmatic_search_without_terms(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);
        $search = Rest::search(ListedVoucherResource::class)->as(new GenericUser(['id' => 1]));

        $page = $search->payload(['source' => 'engine', 'text' => ['value' => '*']])->page();
        $rendered = $search->payload(['source' => 'engine', 'text' => ['value' => '*']])->render();
        $database = $search->payload(['text' => ['value' => '*']])->page();
        $streamed = $search->payload(['text' => ['value' => '*']])->lazy()->all();

        $this->assertSame([[], 0], [$page->items(), $page->total()]);
        $this->assertSame([[], 0], [$rendered['data'], $rendered['total']]);
        $this->assertSame([[], 0], [$database->items(), $database->total()]);
        $this->assertSame([], $streamed);
        $this->assertSame([], $engine->searches);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('actionSearches')]
    public function it_runs_an_action_on_no_row_for_a_text_without_terms(string $resource, array $search): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $response = $this->postJson("/api/{$resource}/actions/deactivate-vouchers", ['search' => ['text' => ['value' => '%'], ...$search]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->assertSame([], $this->received->calls());
        $this->assertSame([], $engine->searches);
        $this->assertSame([true, true, true, true, false, true], Voucher::withTrashed()->orderBy('id')->pluck('active')->all());
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function actionSearches(): array
    {
        return [
            'an action in the database' => ['vouchers', []],
            'an action on an engine list' => ['listed-vouchers', ['source' => 'engine']],
        ];
    }

    #[Test]
    public function it_queues_nothing_for_an_action_on_an_engine_list_without_terms(): void
    {
        Queue::fake();
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $response = $this->postJson('/api/listed-vouchers/actions/queued-deactivate-vouchers', ['search' => ['source' => 'engine', 'text' => ['value' => '*']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        Queue::assertNothingPushed();
        $this->assertSame([], $engine->searches);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('validatedSearches')]
    public function it_validates_a_text_without_terms_as_a_search_without_text(array $search): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $absent = $this->search('listed-vouchers', $search);
        $termless = $this->search('listed-vouchers', ['text' => ['value' => '*'], ...$search]);

        $absent->assertUnprocessable();
        $this->assertSame($absent->json(), $termless->assertUnprocessable()->json());
        $this->assertSame([], $engine->searches);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function validatedSearches(): array
    {
        return [
            'a scope in an engine list' => [['source' => 'engine', 'scopes' => [['name' => 'cheap']]]],
            'the relevance sort in an engine list' => [['source' => 'engine', 'sorts' => [['field' => '@relevance']]]],
            'an attribute the engine does not sort' => [['source' => 'engine', 'sorts' => [['field' => 'title']]]],
            'the relevance sort in the database' => [['sorts' => [['field' => '@relevance']]]],
            'an unknown source' => [['source' => 'index']],
        ];
    }

    #[Test]
    public function it_accepts_the_database_source_with_a_text_without_terms(): void
    {
        $response = $this->search('listed-vouchers', ['source' => 'database', 'text' => ['value' => '*'], 'sorts' => [['field' => 'title']]]);

        $response->assertOk()->assertExactJson($this->sqlPage([], 0));
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('narrowedSearches')]
    public function it_matches_nothing_for_a_text_without_terms_next_to_a_scope_or_trashed_rows(array $search): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => '*'], ...$search]);

        $response->assertOk()->assertExactJson($this->sqlPage([], 0));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function narrowedSearches(): array
    {
        return [
            'a scope' => [['scopes' => [['name' => 'cheap']]]],
            'trashed rows' => [['trashed' => 'with']],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('resolvedTexts')]
    public function it_tells_through_the_request_context_that_a_text_without_terms_matches_nothing(array $search, bool $matchesNothing): void
    {
        $context = $this->context(['search' => $search]);

        $text = $context->text($this->resource(ListedVoucherResource::class, $context));

        $this->assertSame([$matchesNothing, null, null], [$text->matchesNothing(), $text->query, $text->clause()]);
    }

    /** @return array<string, array{array<string, mixed>, bool}> */
    public static function resolvedTexts(): array
    {
        return [
            'a text without terms' => [['text' => ['value' => '*']], true],
            'a text without terms in an engine list' => [['source' => 'engine', 'text' => ['value' => '%']], true],
            'a blank text' => [['text' => ['value' => ' ']], false],
            'no text' => [[], false],
        ];
    }
}

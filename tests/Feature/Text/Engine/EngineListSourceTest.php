<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstHalfExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\MeasuredListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\MeasuredVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineListSourceTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    private const array CHEAP = [['name' => 'cheap']];

    private const string PROHIBITED = 'The search.source field is prohibited.';

    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
        Route::prefix('api')->group(function (): void {
            Rest::resource('listed-vouchers', ListedVoucherController::class)->register();
            Rest::resource('measured-vouchers', MeasuredVoucherController::class)->register();
            Rest::resource('measured-listed-vouchers', MeasuredListedVoucherController::class)->register();
        });
    }

    /** @param array<string, mixed> $source */
    #[Test]
    #[DataProvider('sqlSources')]
    public function it_runs_a_list_without_the_engine_source_as_the_sql_search_with_its_scopes_and_instructions(array $source): void
    {
        config(['rest.extensions' => [FirstHalfExtension::class]]);
        DB::enableQueryLog();

        $response = $this->search('listed-vouchers', [...$source, 'scopes' => self::CHEAP, 'instructions' => [['name' => 'with-lines']]]);

        $response->assertOk()->assertJsonPath('data.*.id', [3])->assertJsonPath('total', 1)->assertJsonMissingPath('dropped');
        $this->assertSame([], $this->engine->searches);
        $this->assertSame(
            'select vouchers.id, vouchers.code, vouchers.number, vouchers.team, vouchers.title, vouchers.price, vouchers.active from vouchers where ((team = ?) and (vouchers.id != ?)) and price < ? and exists (select * from voucher_lines where vouchers.id = voucher_lines.voucher_id) and vouchers.deleted_at is null order by vouchers.id desc limit 50 offset 0',
            str_replace(['"', '`'], '', (string) (Arr::first(DB::getQueryLog(), fn (array $query): bool => str_contains((string) $query['query'], 'limit'))['query'] ?? '')),
        );
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function sqlSources(): array
    {
        return [
            'no source' => [[]],
            'the database source' => [['source' => 'database']],
            'the database source with a blank text' => [['source' => 'database', 'text' => ['value' => ' ']]],
        ];
    }

    /** @param array<string, mixed> $text */
    #[Test]
    #[DataProvider('blankTexts')]
    public function it_runs_a_list_with_the_engine_source_on_the_engine_and_refuses_what_the_engine_does_not_run(array $text): void
    {
        $response = $this->search('listed-vouchers', ['source' => 'engine', ...$text, 'filters' => [['field' => 'price', 'operator' => '<', 'value' => 40]], 'limit' => 2, 'page' => 2]);
        $scoped = $this->search('listed-vouchers', ['source' => 'engine', ...$text, 'scopes' => self::CHEAP]);

        $response->assertOk()->assertExactJson($this->page([2, 3], 4, 2, 2));
        $this->assertSame([[2, 2]], $this->engine->pages);
        $this->assertSame('', $this->engine->searches[0]->query);
        $this->assertSame([['field' => 'price', 'operator' => '<', 'value' => 40]], $this->engine->searches[0]->wheres);
        $this->assertSame([['column' => 'code', 'direction' => 'desc']], $this->engine->searches[0]->orders);
        $scoped->assertUnprocessable()->assertJsonPath('errors', ['search.scopes' => ['The search.scopes field is refused: a list sent with source engine runs on the search engine, which does not run it.']]);
        $this->assertCount(1, $this->engine->searches);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function blankTexts(): array
    {
        return [
            'no text' => [[]],
            'a blank text' => [['text' => ['value' => ' ']]],
        ];
    }

    /** @param array<string, mixed> $source */
    #[Test]
    #[DataProvider('textSources')]
    public function it_runs_a_text_on_the_engine_with_or_without_the_engine_source(array $source): void
    {
        $response = $this->search('listed-vouchers', [...$source, 'text' => ['value' => 'report']]);

        $response->assertOk()->assertJsonPath('total', 4)->assertJsonPath('dropped', 0);
        $this->assertSame('report', $this->engine->searches[0]->query);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function textSources(): array
    {
        return [
            'no source' => [[]],
            'the engine source' => [['source' => 'engine']],
        ];
    }

    #[Test]
    public function it_refuses_the_database_source_with_a_text_and_an_unknown_source(): void
    {
        $text = $this->search('listed-vouchers', ['source' => 'database', 'text' => ['value' => 'report']]);
        $unknown = $this->search('listed-vouchers', ['source' => 'sql']);

        $text->assertUnprocessable()->assertJsonPath('errors', ['search.source' => ['The search.source field cannot be database with a text: a text search runs on the search engine.']]);
        $unknown->assertUnprocessable()->assertJsonPath('errors', ['search.source' => ['The selected search.source is invalid.']]);
        $this->assertSame([], $this->engine->searches);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('unservedSources')]
    public function it_refuses_the_source_of_a_resource_that_does_not_serve_its_lists(string $resource, array $search): void
    {
        $response = $this->search($resource, $search);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.source' => [self::PROHIBITED]]);
        $this->assertSame([], $this->engine->searches);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function unservedSources(): array
    {
        return [
            'the engine on a text-only engine resource' => ['vouchers', ['source' => 'engine']],
            'the engine with a text on a text-only engine resource' => ['vouchers', ['source' => 'engine', 'text' => ['value' => 'report']]],
            'the database on a text-only engine resource' => ['vouchers', ['source' => 'database']],
            'the engine on a resource without text' => ['voucher-lines', ['source' => 'engine']],
        ];
    }

    #[Test]
    public function it_refuses_the_source_of_an_action_and_a_programmatic_search_of_a_resource_that_does_not_serve_its_lists(): void
    {
        $action = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['source' => 'engine']]);

        $action->assertUnprocessable()->assertJsonPath('errors', ['search.source' => [self::PROHIBITED]]);
        $this->expectException(ValidationException::class);

        Rest::search(VoucherResource::class)->as(new GenericUser(['id' => 1]))->payload(['source' => 'engine'])->page();
    }

    #[Test]
    public function it_runs_an_action_on_the_rows_of_the_source(): void
    {
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);

        $sql = $this->postJson('/api/listed-vouchers/actions/deactivate-vouchers', ['search' => ['scopes' => self::CHEAP]]);
        $this->assertSame([], $this->engine->searches);
        $engine = $this->postJson('/api/listed-vouchers/actions/deactivate-vouchers', ['search' => ['source' => 'engine', 'filters' => [['field' => 'price', 'operator' => '<', 'value' => 10]]]]);

        $sql->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $engine->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([['handle', ['ids' => [1, 3]]], ['handle', ['ids' => [1, 3]]]], $received->calls());
        $this->assertCount(1, $this->engine->searches);
    }

    #[Test]
    public function it_queues_the_rows_of_the_source_and_reads_them_again_in_the_worker(): void
    {
        Queue::fake();
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);

        $this->postJson('/api/listed-vouchers/actions/queued-deactivate-vouchers', ['search' => []])->assertOk();
        $this->postJson('/api/listed-vouchers/actions/queued-deactivate-vouchers', ['search' => ['source' => 'engine']])->assertOk();
        Voucher::query()->whereKey(2)->update(['team' => 'blue']);
        [$sql, $engine] = Queue::pushed(QueuedAction::class)->values()->all();
        $this->app->call([$sql, 'handle']);
        $this->app->call([$engine, 'handle']);

        $this->assertSame([[1, 2, 3, 5], true], [$sql->rows->keys, $sql->rows->perimeter]);
        $this->assertSame([[5, 4, 1, 2, 3], false], [$engine->rows->keys, $engine->rows->perimeter]);
        $this->assertSame([['handle', ['ids' => [1, 3, 5]]], ['handle', ['ids' => [5, 4, 1, 2, 3]]]], $received->calls());
    }

    #[Test]
    public function it_batches_the_rows_of_the_source(): void
    {
        Bus::fake();

        $this->postJson('/api/measured-listed-vouchers/actions/batched-deactivate-vouchers', ['search' => ['scopes' => self::CHEAP]])->assertOk();
        $this->postJson('/api/measured-listed-vouchers/actions/batched-deactivate-vouchers', ['search' => ['source' => 'engine', 'filters' => [['field' => 'price', 'operator' => '<', 'value' => 10]]]])->assertOk();

        $batches = [];
        Bus::assertBatched(function (PendingBatch $batch) use (&$batches): bool {
            $batches[] = $batch->jobs->map(fn (QueuedAction $job): array => [$job->rows->keys, $job->rows->perimeter])->all();

            return true;
        });
        $this->assertSame([[[[1, 3], true]], [[[1, 3], false]]], $batches);
    }

    #[Test]
    public function it_follows_the_source_of_a_programmatic_search(): void
    {
        $search = Rest::search(ListedVoucherResource::class)->as(new GenericUser(['id' => 1]));

        $sql = $search->payload(['scopes' => self::CHEAP])->page();
        $this->assertSame([], $this->engine->searches);
        $engine = $search->payload(['source' => 'engine'])->page();
        $rendered = $search->payload(['source' => 'engine', 'selects' => [['field' => 'id']]])->render();

        $this->assertSame([3, 1], array_map(fn (Model $row): mixed => $row->getKey(), $sql->items()));
        $this->assertSame([5, 4, 1, 2, 3], array_map(fn (Model $row): mixed => $row->getKey(), $engine->items()));
        $this->assertSame([5, 4, 1, 2, 3], array_column($rendered['data'], 'id'));
        $this->assertCount(2, $this->engine->searches);
    }

    #[Test]
    public function it_streams_a_programmatic_search_in_sql_and_refuses_the_engine_source_of_a_stream(): void
    {
        $search = Rest::search(ListedVoucherResource::class)->as(new GenericUser(['id' => 1]));

        $streamed = $search->payload(['source' => 'database', 'scopes' => self::CHEAP])->lazy()->all();

        $this->assertSame([3, 1], array_column($streamed, 'id'));

        try {
            $search->payload(['source' => 'engine'])->lazy()->all();
            $this->fail('The stream ran with the engine source.');
        } catch (ValidationException $exception) {
            $this->assertSame(['search.source' => ['The selected search.source is invalid.']], $exception->errors());
        }

        $this->assertSame([], $this->engine->searches);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('metricSources')]
    public function it_refuses_the_source_of_a_metric_search(string $resource, array $search): void
    {
        $response = $this->postJson("/api/{$resource}/metrics/row-count", ['search' => $search]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.source' => [self::PROHIBITED]]);
        $this->assertSame([], $this->engine->searches);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function metricSources(): array
    {
        return [
            'the engine on a resource that serves its lists' => ['measured-listed-vouchers', ['source' => 'engine']],
            'the database on a resource that serves its lists' => ['measured-listed-vouchers', ['source' => 'database']],
            'the engine on a text-only engine resource' => ['measured-vouchers', ['source' => 'engine']],
        ];
    }

    #[Test]
    public function it_refuses_the_source_inside_an_include(): void
    {
        $response = $this->search('listed-vouchers', ['source' => 'engine', 'includes' => [['relation' => 'lines', 'source' => 'engine']]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.includes.0.source' => ['The search.includes.0.source field is prohibited.']]);
        $this->assertSame([], $this->engine->searches);
    }
}

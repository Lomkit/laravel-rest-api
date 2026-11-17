<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstHalfExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\DatabaseListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\DatabaseListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Text\ScoutEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineDatabaseListTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    private const array SQL_SEARCH = ['source' => 'database', 'scopes' => [['name' => 'cheap']], 'instructions' => [['name' => 'with-lines']]];

    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
        Route::prefix('api')->group(function (): void {
            Rest::resource('listed-vouchers', ListedVoucherController::class)->register();
            Rest::resource('database-listed-vouchers', DatabaseListedVoucherController::class)->register();
        });
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('unknownSources')]
    public function it_refuses_the_source_of_a_resource_that_does_not_allow_database_lists(string $resource, array $search): void
    {
        $response = $this->search($resource, $search);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.source' => ['The search.source field is prohibited.']]);
        $this->assertSame([], $this->engine->searches);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function unknownSources(): array
    {
        return [
            'the database on a resource that serves its lists' => ['listed-vouchers', ['source' => 'database']],
            'the engine on a resource that serves its lists' => ['listed-vouchers', ['source' => 'engine']],
            'the database on a resource searched in SQL' => ['vouchers', ['source' => 'database']],
        ];
    }

    #[Test]
    public function it_runs_the_sql_search_with_its_scopes_instructions_perimeter_and_restrictions_on_request(): void
    {
        config(['rest.extensions' => [FirstHalfExtension::class]]);
        DB::enableQueryLog();

        $response = $this->search('database-listed-vouchers', self::SQL_SEARCH);

        $response->assertOk()->assertJsonPath('data.*.id', [3])->assertJsonPath('total', 1)->assertJsonMissingPath('dropped');
        $this->assertSame([], $this->engine->searches);
        $this->assertSame(
            'select vouchers.id, vouchers.code, vouchers.number, vouchers.team, vouchers.title, vouchers.price, vouchers.active from vouchers where ((team = ?) and (vouchers.id != ?)) and price < ? and exists (select * from voucher_lines where vouchers.id = voucher_lines.voucher_id) and vouchers.deleted_at is null order by vouchers.id desc limit 50 offset 0',
            str_replace(['"', '`'], '', (string) (Arr::first(DB::getQueryLog(), fn (array $query): bool => str_contains((string) $query['query'], 'limit'))['query'] ?? '')),
        );
    }

    /** @param array<string, mixed> $source */
    #[Test]
    #[DataProvider('engineSources')]
    public function it_keeps_the_lists_on_the_engine_without_the_database_source(array $source): void
    {
        $response = $this->search('database-listed-vouchers', $source);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 2, 3], 5));
        $this->assertCount(1, $this->engine->searches);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function engineSources(): array
    {
        return [
            'no source' => [[]],
            'the engine source' => [['source' => 'engine']],
        ];
    }

    #[Test]
    public function it_runs_a_text_on_the_engine_and_refuses_the_database_source_with_it(): void
    {
        $refused = $this->search('database-listed-vouchers', ['source' => 'database', 'text' => ['value' => 'report']]);
        $blank = $this->search('database-listed-vouchers', ['source' => 'database', 'text' => ['value' => ' '], 'scopes' => [['name' => 'cheap']]]);
        $unknown = $this->search('database-listed-vouchers', ['source' => 'sql']);

        $refused->assertUnprocessable()->assertJsonPath('errors', ['search.source' => ['The search.source field cannot be database with a text: a text search runs on the search engine.']]);
        $blank->assertOk()->assertJsonPath('data.*.id', [3, 1]);
        $unknown->assertUnprocessable()->assertJsonPath('errors', ['search.source' => ['The selected search.source is invalid.']]);
        $this->assertSame([], $this->engine->searches);
    }

    #[Test]
    public function it_refuses_the_source_inside_an_include(): void
    {
        $response = $this->search('database-listed-vouchers', ['includes' => [['relation' => 'lines', 'source' => 'database']]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.includes.0.source' => ['The search.includes.0.source field is prohibited.']]);
    }

    #[Test]
    public function it_runs_an_action_on_the_sql_rows_of_the_database_source(): void
    {
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);

        $response = $this->postJson('/api/database-listed-vouchers/actions/deactivate-vouchers', ['search' => ['source' => 'database', 'scopes' => [['name' => 'cheap']]]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([['handle', ['ids' => [1, 3]]]], $received->calls());
        $this->assertSame([], $this->engine->searches);
    }

    #[Test]
    public function it_follows_the_source_of_a_programmatic_search(): void
    {
        $search = Rest::search(DatabaseListedVoucherResource::class)->as(new GenericUser(['id' => 1]));

        $database = $search->payload(['source' => 'database', 'scopes' => [['name' => 'cheap']]])->page();
        $this->assertSame([], $this->engine->searches);
        $engine = $search->payload(['source' => 'engine'])->page();

        $this->assertSame([3, 1], array_map(fn (Model $row): mixed => $row->getKey(), $database->items()));
        $this->assertSame([5, 4, 1, 2, 3], array_map(fn (Model $row): mixed => $row->getKey(), $engine->items()));
        $this->assertCount(1, $this->engine->searches);
    }

    #[Test]
    public function it_describes_the_database_lists_of_the_resource(): void
    {
        $allowed = $this->getJson('/api/database-listed-vouchers?with=text');
        $engine = $this->getJson('/api/listed-vouchers?with=text');

        $allowed->assertOk()->assertJsonPath('data.text.engine.database_lists', true);
        $engine->assertOk()->assertJsonMissingPath('data.text.engine.database_lists');
    }

    #[Test]
    public function it_refuses_database_lists_without_served_lists(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::unlistedDatabaseLists(VoucherResource::class));

        ScoutEngine::make()->filterable(['price'])->withoutPerimeter()->allowsDatabaseLists()->capabilities($this->target(VoucherResource::class));
    }
}

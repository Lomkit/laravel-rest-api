<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\EngineManager;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedTeamVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineListTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    private const string REFUSED = 'field is refused: this resource serves its lists through its search engine, which does not run it.';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(function (): void {
            Rest::resource('listed-vouchers', ListedVoucherController::class)->withSoftDeletes();
            Rest::resource('listed-team-vouchers', ListedTeamVoucherController::class)->withSoftDeletes();
        });
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_lists_the_rows_through_the_engine_in_key_order_with_the_engine_total(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('listed-vouchers', []);

        $response->assertOk()->assertExactJson($this->page([3, 2, 1, 4, 5], 5));
    }

    #[Test]
    public function it_sends_the_match_all_query_the_filters_the_key_order_and_the_page_to_the_engine(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $response = $this->search('listed-vouchers', ['filters' => [['field' => 'price', 'operator' => '<', 'value' => 40]], 'limit' => 2, 'page' => 2]);

        $response->assertOk()->assertExactJson($this->page([1, 4], 4, 2, 2));
        $this->assertSame([[2, 2]], $engine->pages);
        $this->assertSame('', $engine->searches[0]->query);
        $this->assertSame([['field' => 'price', 'operator' => '<', 'value' => 40]], $engine->searches[0]->wheres);
        $this->assertSame([['column' => 'code', 'direction' => 'asc']], $engine->searches[0]->orders);
    }

    #[Test]
    public function it_keeps_the_requested_sort_in_place_of_the_key_order(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $response = $this->search('listed-vouchers', ['sorts' => [['field' => 'price', 'direction' => 'desc']]]);

        $response->assertOk()->assertJsonPath('data.*.id', [5, 2, 4, 3, 1]);
        $this->assertSame([['column' => 'price', 'direction' => 'desc']], $engine->searches[0]->orders);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('lists')]
    public function it_filters_sorts_and_pages_a_list_on_the_engine(string $driver, array $search, array $ids, int $total, int $perPage, int $page): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('listed-vouchers', $search);

        $response->assertOk()->assertExactJson($this->page($ids, $total, $perPage, $page));
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<int>, int, int, int}> */
    public static function lists(): iterable
    {
        foreach (self::drivers() as $engine => [$driver]) {
            yield "integers in a list on {$engine}" => [$driver, ['filters' => [['field' => 'number', 'operator' => 'in', 'value' => [1, 3]]]], [3, 1], 2, 50, 1];
            yield "a false boolean on {$engine}" => [$driver, ['filters' => [['field' => 'active', 'value' => false]]], [5], 1, 50, 1];
            yield "two sorts on {$engine}" => [$driver, ['sorts' => [['field' => 'number', 'direction' => 'desc'], ['field' => 'price']]], [5, 4, 1, 2, 3], 5, 50, 1];
            yield "the second page on {$engine}" => [$driver, ['limit' => 2, 'page' => 2], [1, 4], 5, 2, 2];
            yield "a page past the last on {$engine}" => [$driver, ['limit' => 2, 'page' => 4], [], 5, 2, 4];
        }
    }

    /**
     * @param  list<string>  $teams
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('perimeters')]
    public function it_keeps_a_list_inside_the_index_perimeter(array $teams, array $ids): void
    {
        config(['scout.driver' => 'database']);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => $teams]));

        $response = $this->search('listed-team-vouchers', []);

        $response->assertOk()->assertExactJson($this->page($ids, count($ids)));
    }

    /** @return array<string, array{list<string>, list<int>}> */
    public static function perimeters(): array
    {
        return [
            'two teams' => [['red', 'blue'], [3, 2, 1, 4, 5]],
            'one team' => [['blue'], [4]],
            'no team' => [[], []],
        ];
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_lists_the_trashed_rows_the_index_keeps(string $driver): void
    {
        config(['scout.driver' => $driver, 'scout.soft_delete' => true]);

        $with = $this->search('listed-vouchers', ['trashed' => 'with']);
        $only = $this->search('listed-vouchers', ['trashed' => 'only']);

        $with->assertOk()->assertExactJson($this->page([3, 2, 1, 4, 5, 6], 6));
        $only->assertOk()->assertExactJson($this->page([6], 1));
    }

    #[Test]
    public function it_loads_the_includes_and_aggregates_of_the_listed_rows(): void
    {
        config(['scout.driver' => 'database']);

        $response = $this->search('listed-vouchers', [
            'includes' => [['relation' => 'lines']],
            'aggregates' => [['relation' => 'lines', 'type' => 'count']],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [3, 2, 1, 4, 5])
            ->assertJsonPath('data.*.lines_count', [1, 0, 2, 1, 1])
            ->assertJsonPath('data.2.lines.*.label', ['l1b', 'l1a']);
    }

    /**
     * @param  array<string, mixed>  $search
     */
    #[Test]
    #[DataProvider('refused')]
    public function it_refuses_what_the_engine_does_not_run_in_a_list(array $search, string $key): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $response = $this->search('listed-vouchers', $search);

        $response->assertUnprocessable()->assertJsonPath('errors', [$key => ["The {$key} ".self::REFUSED]]);
        $this->assertSame([], $engine->searches);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function refused(): array
    {
        return [
            'a scope' => [['scopes' => [['name' => 'cheap']]], 'search.scopes'],
            'a relation path in a filter' => [['filters' => [['field' => 'lines.label', 'value' => 'l1a']]], 'search.filters.0.field'],
            'an attribute the engine does not filter' => [['filters' => [['field' => 'title', 'value' => 'Budget']]], 'search.filters.0.field'],
            'a relation path in a sort' => [['sorts' => [['field' => 'lines.label']]], 'search.sorts.0.field'],
            'an attribute the engine does not sort' => [['sorts' => [['field' => 'title']]], 'search.sorts.0.field'],
            'an instruction that runs in the database only' => [['instructions' => [['name' => 'with-lines']]], 'search.instructions.0.name'],
            'the relevance sort without text' => [['sorts' => [['field' => '@relevance']]], 'search.sorts.0.field'],
            'the relevance sort with a blank text' => [['text' => ['value' => ' '], 'sorts' => [['field' => '@relevance']]], 'search.sorts.0.field'],
            'trashed rows that the index does not keep' => [['trashed' => 'with'], 'search.trashed'],
        ];
    }

    #[Test]
    public function it_keeps_the_relevance_sort_and_the_text_grammar_for_a_text(): void
    {
        config(['scout.driver' => 'database']);

        $response = $this->search('listed-vouchers', ['text' => ['value' => 'report'], 'sorts' => [['field' => '@relevance']]]);

        $response->assertOk()->assertJsonPath('total', 4);
    }

    #[Test]
    public function it_lists_a_resource_that_does_not_serve_its_lists_in_the_database(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);
        DB::enableQueryLog();

        $response = $this->search('vouchers', ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => 'Report%']], 'scopes' => [['name' => 'cheap']]]);

        $response->assertOk()->assertExactJson($this->sqlPage([3, 1], 2));
        $this->assertSame([], $engine->searches);
        $this->assertSame(
            'select vouchers.id, vouchers.code, vouchers.number, vouchers.team, vouchers.title, vouchers.price, vouchers.active from vouchers where (team = ?) and (vouchers.title like ?) and price < ? and vouchers.deleted_at is null order by vouchers.id desc limit 50 offset 0',
            str_replace(['"', '`', '::text'], '', (string) Arr::last(DB::getQueryLog())['query']),
        );
    }

    #[Test]
    public function it_refuses_to_serve_lists_on_an_engine_whose_profile_does_not_list_without_text(): void
    {
        $this->app->make(EngineManager::class)->extend('foreign', fn (): ForeignEngine => new ForeignEngine);
        config(['scout.driver' => 'foreign', 'rest.extensions' => [ForeignEngineProfiles::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unlistedEngine(ListedVoucherResource::class, ForeignEngine::class));

        $this->search('listed-vouchers', []);
    }

    #[Test]
    public function it_pages_a_programmatic_search_through_the_engine_and_streams_it_in_the_database(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);
        $search = Rest::search(ListedVoucherResource::class)->as(new GenericUser(['id' => 1]))->payload(['selects' => [['field' => 'id']]]);

        $page = $search->page();
        $rendered = $search->render();
        $streamed = $search->lazy()->all();

        $this->assertSame([3, 2, 1, 4, 5], array_map(fn (Model $row): mixed => $row->getKey(), $page->items()));
        $this->assertSame([[50, 1], [50, 1]], $engine->pages);
        $this->assertSame([3, 2, 1, 4, 5], array_column($rendered['data'], 'id'));
        $this->assertSame([5, 3, 2, 1], array_column($streamed, 'id'));
    }
}

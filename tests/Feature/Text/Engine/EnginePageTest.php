<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use ArrayObject;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EnginePageTest extends EngineSearchTestCase
{
    #[Test]
    #[DataProvider('drivers')]
    public function it_keeps_the_text_search_inside_the_index_perimeter(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));

        $both = $this->search('team-vouchers', ['text' => ['value' => 'report']]);
        $this->actingAs(new GenericUser(['id' => 2, 'teams' => ['blue']]));
        $blue = $this->search('team-vouchers', ['text' => ['value' => 'report']]);

        $both->assertOk()->assertJsonPath('data.*.id', [5, 4, 1, 3])->assertJsonPath('total', 4);
        $blue->assertOk()->assertExactJson($this->page([4], 1));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_returns_an_empty_page_when_the_index_perimeter_matches_nothing(string $driver): void
    {
        config(['scout.driver' => $driver]);
        DB::enableQueryLog();

        $response = $this->search('team-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([], 0));
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_selects_the_fields_of_the_page_rows(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'selects' => [['field' => 'code'], ['field' => 'title']]]);

        $response->assertOk()->assertJsonPath('data', [
            ['code' => 'V-e', 'title' => 'Report inactive'],
            ['code' => 'V-c', 'title' => 'Report alpha'],
            ['code' => 'V-a', 'title' => 'Report beta'],
        ]);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_loads_the_includes_and_aggregates_of_the_page_rows(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('vouchers', [
            'text' => ['value' => 'report'],
            'includes' => [['relation' => 'lines']],
            'aggregates' => [['relation' => 'lines', 'type' => 'count']],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [5, 1, 3])
            ->assertJsonPath('data.*.lines_count', [1, 2, 1])
            ->assertJsonPath('data.0.lines.*.label', ['l5a'])
            ->assertJsonPath('data.1.lines.*.label', ['l1b', 'l1a'])
            ->assertJsonPath('data.2.lines.*.label', ['l3a']);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_filters_the_includes_and_aggregates_of_the_page_rows_with_the_database_grammar(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('vouchers', [
            'text' => ['value' => 'report'],
            'includes' => [['relation' => 'lines', 'filters' => [
                ['field' => 'label', 'operator' => 'like', 'value' => '%b'],
                ['field' => 'label', 'value' => 'l3a', 'type' => 'or'],
            ]]],
            'aggregates' => [['relation' => 'lines', 'type' => 'count', 'filters' => [
                ['field' => 'label', 'operator' => 'like', 'value' => '%b'],
                ['field' => 'label', 'value' => 'l3a', 'type' => 'or'],
            ]]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [5, 1, 3])
            ->assertJsonPath('data.*.lines_count', [0, 1, 1])
            ->assertJsonPath('data.0.lines', [])
            ->assertJsonPath('data.1.lines.*.label', ['l1b'])
            ->assertJsonPath('data.2.lines.*.label', ['l3a']);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_loads_the_rows_of_integer_scout_keys(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $alpha = $this->search('numbered-vouchers', ['text' => ['value' => 'alpha']]);
        $report = $this->search('numbered-vouchers', ['text' => ['value' => 'report'], 'sorts' => [['field' => 'price']]]);

        $alpha->assertOk()->assertExactJson($this->page([1], 1));
        $report->assertOk()->assertJsonPath('data.*.id', [1, 3, 5])->assertJsonPath('total', 4);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_keeps_the_global_scopes_of_the_model_on_the_page(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('scoped-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertJsonPath('data.*.id', [1, 3])->assertJsonPath('total', 3);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_searches_the_letters_and_digits_of_the_text_only(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $percent = $this->search('vouchers', ['text' => ['value' => 'R%a']]);
        $underscore = $this->search('vouchers', ['text' => ['value' => 'R_port']]);
        $quoted = $this->search('vouchers', ['text' => ['value' => '"alpha"!']]);

        $percent->assertOk()->assertExactJson($this->page([], 0));
        $underscore->assertOk()->assertExactJson($this->page([], 0));
        $quoted->assertOk()->assertExactJson($this->page([1], 1));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_loads_the_page_in_one_query_bound_to_the_perimeter_then_to_the_keys(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $pages = $this->pageQueries();

        $response = $this->search('vouchers', [
            'text' => ['value' => 'report'],
            'filters' => [['field' => 'price', 'operator' => '>=', 'value' => 1]],
            'sorts' => [['field' => 'price', 'direction' => 'desc']],
            'instructions' => [['name' => 'numbered-from', 'fields' => [['name' => 'min', 'value' => 1]]]],
            'limit' => 2,
            'page' => 2,
        ]);

        $response->assertOk()->assertExactJson($this->page([3, 1], 4, 2, 2));
        $this->assertSame([['red', 'V-a', 'V-c']], array_map(fn (QueryExecuted $query): array => $query->bindings, $pages->getArrayCopy()));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_loads_no_page_when_the_engine_finds_nothing(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $pages = $this->pageQueries();

        $response = $this->search('vouchers', ['text' => ['value' => 'invoice']]);

        $response->assertOk()->assertExactJson($this->page([], 0));
        $this->assertSame([], $pages->getArrayCopy());
    }

    /** @return ArrayObject<int, QueryExecuted> */
    private function pageQueries(): ArrayObject
    {
        $pages = new ArrayObject;
        $inList = DB::getQueryGrammar()->wrap('vouchers.code').' in (';

        DB::listen(function (QueryExecuted $query) use ($pages, $inList): void {
            if (str_contains($query->sql, $inList)) {
                $pages->append($query);
            }
        });

        return $pages;
    }
}

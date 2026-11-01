<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

trait SearchesAnIndex
{
    private const array BY_PRICE = [['field' => 'price', 'direction' => 'asc']];

    #[Test]
    public function it_returns_the_rows_matching_the_text_with_the_engine_total(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE]);

        $response->assertOk()->assertExactJson($this->page([1, 3, 5], 4));
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('filters')]
    public function it_filters_the_text_search_on_the_engine(array $filters, array $ids, int $total): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => $filters, 'sorts' => self::BY_PRICE]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', $ids)
            ->assertJsonPath('total', $total);
    }

    /** @return array<string, array{list<array<string, mixed>>, list<int>, int}> */
    public static function filters(): array
    {
        return [
            'an integer equal to' => [[['field' => 'number', 'value' => 3]], [1], 1],
            'an integer different from' => [[['field' => 'number', 'operator' => '!=', 'value' => '3']], [3, 5], 3],
            'a number greater than' => [[['field' => 'price', 'operator' => '>', 'value' => 7.25]], [5], 2],
            'a number at least' => [[['field' => 'price', 'operator' => '>=', 'value' => '7.25']], [3, 5], 3],
            'a number less than' => [[['field' => 'price', 'operator' => '<', 'value' => 7.25]], [1], 1],
            'a number at most' => [[['field' => 'price', 'operator' => '<=', 'value' => 7.25]], [1, 3], 2],
            'a number equal to a decimal string' => [[['field' => 'price', 'value' => '1.5']], [1], 1],
            'decimals in a list' => [[['field' => 'price', 'operator' => 'in', 'value' => [1.5, '7.25']]], [1, 3], 2],
            'integers in a list' => [[['field' => 'number', 'operator' => 'in', 'value' => [1, '3']]], [1, 3], 2],
            'integers outside a list' => [[['field' => 'number', 'operator' => 'not in', 'value' => [1, 3]]], [5], 2],
            'a false boolean' => [[['field' => 'active', 'value' => false]], [5], 1],
            'a true boolean with an and type' => [[['field' => 'active', 'value' => true, 'type' => 'and']], [1, 3], 3],
            'a text equal to' => [[['field' => 'code', 'value' => 'V-a']], [3], 1],
            'texts in a list' => [[['field' => 'code', 'operator' => 'in', 'value' => ['V-a', 'V-e']]], [3, 5], 2],
            'texts outside a list' => [[['field' => 'code', 'operator' => 'not in', 'value' => ['V-a', 'V-e']]], [1], 2],
            'every filter' => [[
                ['field' => 'price', 'operator' => '>', 'value' => 1],
                ['field' => 'active', 'value' => true],
                ['field' => 'number', 'operator' => 'in', 'value' => [1, 4, 5]],
            ], [3], 2],
        ];
    }

    /**
     * @param  list<array<string, string>>  $sorts
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('sorts')]
    public function it_sorts_the_text_search_on_the_engine(array $sorts, array $ids): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'sorts' => $sorts]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', $ids)
            ->assertJsonPath('total', 4);
    }

    /** @return array<string, array{list<array<string, string>>, list<int>}> */
    public static function sorts(): array
    {
        return [
            'a number in ascending order' => [self::BY_PRICE, [1, 3, 5]],
            'a number in descending order' => [[['field' => 'price', 'direction' => 'desc']], [5, 3, 1]],
            'an integer without direction' => [[['field' => 'number']], [3, 1, 5]],
            'two attributes' => [[['field' => 'number', 'direction' => 'desc'], ['field' => 'price']], [5, 1, 3]],
        ];
    }

    #[Test]
    public function it_keeps_the_order_of_the_engine_for_the_relevance_sort(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'sorts' => [['field' => '@relevance']]]);

        $response->assertOk()->assertJsonPath('total', 4);
        $this->assertEqualsCanonicalizing([1, 3, 5], $response->json('data.*.id'));
    }

    #[Test]
    public function it_pages_on_the_engine_and_keeps_the_engine_total_when_the_page_loses_rows(): void
    {
        $first = $this->search('vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE, 'limit' => 2]);
        $second = $this->search('vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE, 'limit' => 2, 'page' => 2]);
        $past = $this->search('vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE, 'limit' => 2, 'page' => 3]);

        $first->assertOk()->assertExactJson($this->page([1, 3], 4, 2));
        $second->assertOk()->assertExactJson($this->page([5], 4, 2, 2));
        $past->assertOk()->assertExactJson($this->page([], 4, 2, 3));
    }

    /**
     * @param  list<string>  $teams
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('perimeters')]
    public function it_keeps_the_text_search_inside_the_index_perimeter(array $teams, array $ids): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => $teams]));

        $response = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE]);

        $response->assertOk()->assertExactJson($this->page($ids, count($ids)));
    }

    /** @return array<string, array{list<string>, list<int>}> */
    public static function perimeters(): array
    {
        return [
            'two teams' => [['red', 'blue'], [1, 3, 4, 5]],
            'one team' => [['blue'], [4]],
            'no team' => [[], []],
        ];
    }

    /**
     * @param  array<string, mixed>  $trashed
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('trashedSearches')]
    public function it_searches_the_trashed_rows_the_index_keeps(array $trashed, array $ids, int $total): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE, ...$trashed]);

        $response->assertOk()->assertExactJson($this->page($ids, $total));
    }

    /** @return array<string, array{array<string, mixed>, list<int>, int}> */
    public static function trashedSearches(): array
    {
        return [
            'with the trashed rows' => [['trashed' => 'with'], [1, 6, 3, 5], 5],
            'only the trashed rows' => [['trashed' => 'only'], [6], 1],
        ];
    }

    #[Test]
    public function it_loads_the_includes_and_aggregates_of_the_page_rows(): void
    {
        $response = $this->search('vouchers', [
            'text' => ['value' => 'report'],
            'sorts' => self::BY_PRICE,
            'includes' => [['relation' => 'lines']],
            'aggregates' => [['relation' => 'lines', 'type' => 'count']],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [1, 3, 5])
            ->assertJsonPath('data.*.lines_count', [2, 1, 1])
            ->assertJsonPath('data.0.lines.*.label', ['l1b', 'l1a'])
            ->assertJsonPath('data.1.lines.*.label', ['l3a'])
            ->assertJsonPath('data.2.lines.*.label', ['l5a']);
    }

    #[Test]
    public function it_keeps_the_global_scopes_of_the_model_on_the_page(): void
    {
        $response = $this->search('scoped-vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [1, 3])
            ->assertJsonPath('total', 4);
    }
}

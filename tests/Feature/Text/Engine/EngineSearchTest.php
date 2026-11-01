<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineSearchTest extends EngineSearchTestCase
{
    #[Test]
    #[DataProvider('drivers')]
    public function it_returns_the_rows_matching_the_text_in_engine_order_with_the_engine_total(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 1, 3], 4));
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('filters')]
    public function it_filters_the_text_search_on_the_engine(string $driver, array $filter, array $ids, int $total): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [$filter]]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', $ids)
            ->assertJsonPath('total', $total);
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<int>, int}> */
    public static function filters(): iterable
    {
        $filters = [
            'an integer equal to' => [['field' => 'number', 'value' => 3], [1], 1],
            'an integer different from' => [['field' => 'number', 'operator' => '!=', 'value' => '3'], [5, 3], 3],
            'a number greater than' => [['field' => 'price', 'operator' => '>', 'value' => 7.25], [5], 2],
            'a number at least' => [['field' => 'price', 'operator' => '>=', 'value' => '7.25'], [5, 3], 3],
            'a number less than' => [['field' => 'price', 'operator' => '<', 'value' => 7.25], [1], 1],
            'a number at most' => [['field' => 'price', 'operator' => '<=', 'value' => 7.25], [1, 3], 2],
            'a number equal to a decimal string' => [['field' => 'price', 'value' => '1.5'], [1], 1],
            'integers in a list' => [['field' => 'number', 'operator' => 'in', 'value' => [1, '3']], [1, 3], 2],
            'integers outside a list' => [['field' => 'number', 'operator' => 'not in', 'value' => [1, 3]], [5], 2],
            'a false boolean' => [['field' => 'active', 'value' => false], [5], 1],
            'a true boolean with an and type' => [['field' => 'active', 'value' => true, 'type' => 'and'], [1, 3], 3],
            'a text equal to' => [['field' => 'code', 'value' => 'V-a'], [3], 1],
            'texts in a list' => [['field' => 'code', 'operator' => 'in', 'value' => ['V-a', 'V-e']], [5, 3], 2],
            'texts outside a list' => [['field' => 'code', 'operator' => 'not in', 'value' => ['V-a', 'V-e']], [1], 2],
        ];

        foreach (self::drivers() as $engine => [$driver]) {
            foreach ($filters as $name => [$filter, $ids, $total]) {
                yield "{$name} on {$engine}" => [$driver, $filter, $ids, $total];
            }
        }
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_ands_every_filter_of_the_text_search(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [
            ['field' => 'price', 'operator' => '>', 'value' => 1],
            ['field' => 'active', 'value' => true],
            ['field' => 'number', 'operator' => 'in', 'value' => [1, 4, 5]],
        ]]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [3])
            ->assertJsonPath('total', 2);
    }

    /**
     * @param  list<array<string, string>>  $sorts
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('sorts')]
    public function it_sorts_the_text_search_on_the_engine(string $driver, array $sorts, array $ids): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'sorts' => $sorts]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', $ids)
            ->assertJsonPath('total', 4);
    }

    /** @return iterable<string, array{string, list<array<string, string>>, list<int>}> */
    public static function sorts(): iterable
    {
        $sorts = [
            'a number in ascending order' => [[['field' => 'price', 'direction' => 'asc']], [1, 3, 5]],
            'a number in descending order' => [[['field' => 'price', 'direction' => 'desc']], [5, 3, 1]],
            'an integer without direction' => [[['field' => 'number']], [3, 1, 5]],
            'two attributes' => [[['field' => 'number', 'direction' => 'desc'], ['field' => 'price']], [5, 1, 3]],
            'the engine order' => [[['field' => '@relevance']], [5, 1, 3]],
            'the engine order in descending order' => [[['field' => '@relevance', 'direction' => 'desc']], [5, 1, 3]],
        ];

        foreach (self::drivers() as $engine => [$driver]) {
            foreach ($sorts as $name => [$sort, $ids]) {
                yield "{$name} on {$engine}" => [$driver, $sort, $ids];
            }
        }
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_pages_on_the_engine_and_keeps_the_engine_total_when_the_page_loses_rows(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $first = $this->search('vouchers', ['text' => ['value' => 'report'], 'limit' => 2]);
        $second = $this->search('vouchers', ['text' => ['value' => 'report'], 'limit' => 2, 'page' => 2]);
        $past = $this->search('vouchers', ['text' => ['value' => 'report'], 'limit' => 2, 'page' => 3]);

        $first->assertOk()->assertExactJson($this->page([5], 4, 2));
        $second->assertOk()->assertExactJson($this->page([1, 3], 4, 2, 2));
        $past->assertOk()->assertExactJson($this->page([], 4, 2, 3));
    }
}

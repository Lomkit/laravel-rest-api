<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineTrashedSearchTest extends EngineSearchTestCase
{
    /**
     * @param  array<string, mixed>  $trashed
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('trashedSearches')]
    public function it_searches_the_trashed_rows_the_index_keeps(string $driver, array $trashed, array $ids, int $total): void
    {
        config(['scout.driver' => $driver, 'scout.soft_delete' => true]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], ...$trashed]);

        $response->assertOk()->assertExactJson($this->page($ids, $total));
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<int>, int}> */
    public static function trashedSearches(): iterable
    {
        foreach (self::drivers() as $engine => [$driver]) {
            yield "without the trashed rows on {$engine}" => [$driver, [], [5, 1, 3], 4];
            yield "with the trashed rows on {$engine}" => [$driver, ['trashed' => 'with'], [6, 5, 1, 3], 5];
            yield "only the trashed rows on {$engine}" => [$driver, ['trashed' => 'only'], [6], 1];
        }
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_filters_the_trashed_rows_on_the_engine(string $driver): void
    {
        config(['scout.driver' => $driver, 'scout.soft_delete' => true]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'trashed' => 'with', 'filters' => [['field' => 'price', 'operator' => '<', 'value' => 6]]]);

        $response->assertOk()->assertExactJson($this->page([6, 1], 2));
    }

    #[Test]
    public function it_keeps_the_trashed_rows_of_a_search_without_terms_when_the_index_drops_them(): void
    {
        config(['scout.soft_delete' => false]);

        $response = $this->search('vouchers', ['text' => ['value' => ' '], 'trashed' => 'only']);

        $response->assertOk()->assertExactJson($this->page([6], 1));
    }
}

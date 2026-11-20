<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Totals\Stubs\ScopedShowController;
use PHPUnit\Framework\Attributes\Test;

class SqlSearchTotalsTest extends SearchTotalsTestCase
{
    private const array MEASURES = [
        ['type' => 'sum', 'field' => 'seats'],
        ['type' => 'avg', 'field' => 'seats'],
        ['type' => 'min', 'field' => 'seats'],
        ['type' => 'max', 'field' => 'seats'],
        ['type' => 'sum', 'field' => 'price'],
        ['type' => 'avg', 'field' => 'price'],
        ['type' => 'min', 'field' => 'price'],
        ['type' => 'max', 'field' => 'price'],
    ];

    #[Test]
    public function it_returns_the_totals_at_the_root_of_the_page_next_to_the_total(): void
    {
        $response = $this->search([
            'selects' => [['field' => 'id']],
            'totals' => ['measures' => self::MEASURES, 'groups' => [['field' => 'status'], ['field' => 'level'], ['field' => 'featured']]],
            'limit' => 10,
        ]);

        $response->assertOk();
        $this->assertSame(['current_page', 'data', 'from', 'last_page', 'per_page', 'to', 'total', 'totals', 'meta'], array_keys($response->json()));
        $this->assertSame(5, $response->json('total'));
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 5, 'sum_seats' => 750, 'avg_seats' => 150, 'min_seats' => 50, 'max_seats' => 300, 'sum_price' => 43, 'avg_price' => 10.75, 'min_price' => 5, 'max_price' => 20.25],
            'groups' => [
                ['field' => 'status', 'buckets' => [['value' => 'on_sale', 'count' => 3], ['value' => 'planned', 'count' => 1], ['value' => 'sold_out', 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'level', 'buckets' => [['value' => 1, 'count' => 3], ['value' => 2, 'count' => 1], ['value' => 3, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'featured', 'buckets' => [['value' => false, 'count' => 3], ['value' => true, 'count' => 2]], 'other' => ['count' => 0], 'truncated' => false],
            ],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_counts_the_rows_that_the_filters_of_the_page_keep_and_cuts_a_group_at_its_limit(): void
    {
        $response = $this->search([
            'filters' => [['field' => 'status', 'value' => 'on_sale']],
            'totals' => ['measures' => [['type' => 'sum', 'field' => 'seats']], 'groups' => [['field' => 'title', 'limit' => 1], ['field' => 'seats']]],
        ]);

        $response->assertOk()->assertJsonPath('total', 3);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 3, 'sum_seats' => 400],
            'groups' => [
                ['field' => 'title', 'buckets' => [['value' => 'Jazz', 'count' => 2]], 'other' => ['count' => 1], 'truncated' => true],
                ['field' => 'seats', 'buckets' => [['value' => 100, 'count' => 2], ['value' => 200, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
            ],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_counts_the_rows_without_a_value_in_other_without_marking_the_group_truncated(): void
    {
        $response = $this->search(['totals' => ['groups' => [['field' => 'summary', 'limit' => 2], ['field' => 'summary'], ['field' => 'title', 'limit' => 3]]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals.groups.1.field' => ['The search.totals.groups.1.field field has a duplicate value.']]);

        $limited = $this->search(['totals' => ['groups' => [['field' => 'summary', 'limit' => 1]]]]);
        $kept = $this->search(['totals' => ['groups' => [['field' => 'summary', 'limit' => 2]]]]);

        $this->assertSame([['field' => 'summary', 'buckets' => [['value' => 'Great', 'count' => 2]], 'other' => ['count' => 3], 'truncated' => true]], $limited->json('totals.groups'));
        $this->assertSame([['field' => 'summary', 'buckets' => [['value' => 'Great', 'count' => 2], ['value' => 'Calm', 'count' => 1]], 'other' => ['count' => 2], 'truncated' => false]], $kept->json('totals.groups'));
    }

    #[Test]
    public function it_returns_a_zero_sum_and_null_measures_over_no_row(): void
    {
        $response = $this->search([
            'filters' => [['field' => 'status', 'value' => 'sold_out'], ['field' => 'featured', 'value' => false]],
            'totals' => ['measures' => self::MEASURES, 'groups' => [['field' => 'status']]],
        ]);

        $response->assertOk()->assertJsonPath('total', 0);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 0, 'sum_seats' => 0, 'avg_seats' => null, 'min_seats' => null, 'max_seats' => null, 'sum_price' => 0, 'avg_price' => null, 'min_price' => null, 'max_price' => null],
            'groups' => [['field' => 'status', 'buckets' => [], 'other' => ['count' => 0], 'truncated' => false]],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_returns_empty_totals_for_a_text_without_a_term_without_a_totals_query(): void
    {
        DB::enableQueryLog();

        $response = $this->search(['text' => ['value' => '***'], 'totals' => ['measures' => [['type' => 'max', 'field' => 'seats']], 'groups' => [['field' => 'status']]]]);

        $response->assertOk()->assertJsonPath('total', 0);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 0, 'max_seats' => null],
            'groups' => [['field' => 'status', 'buckets' => [], 'other' => ['count' => 0], 'truncated' => false]],
        ], $response->json('totals'));
        $this->assertSame([], array_filter(array_column(DB::getQueryLog(), 'query'), static fn (string $query): bool => str_contains($query, 'rest_count')));
    }

    #[Test]
    public function it_counts_the_rows_that_a_text_search_keeps(): void
    {
        $response = $this->search(['text' => ['value' => 'jazz'], 'totals' => ['groups' => [['field' => 'level']]]]);

        $response->assertOk()->assertJsonPath('total', 2);
        $this->assertSame([['field' => 'level', 'buckets' => [['value' => 1, 'count' => 1], ['value' => 2, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false]], $response->json('totals.groups'));
    }

    #[Test]
    public function it_counts_the_rows_of_a_scope_that_groups_and_joins_as_the_total_does(): void
    {
        Route::prefix('api')->group(static function (): void {
            Rest::resource('scoped-shows', ScopedShowController::class)->register();
        });
        DB::table('comments')->insert([
            ['show_id' => 1, 'rating' => 5, 'body' => 'Loved it'],
            ['show_id' => 1, 'rating' => 4, 'body' => 'Good'],
            ['show_id' => 2, 'rating' => 3, 'body' => 'Fine'],
            ['show_id' => 4, 'rating' => 2, 'body' => 'Loud'],
            ['show_id' => 4, 'rating' => 5, 'body' => 'Great'],
            ['show_id' => 4, 'rating' => 1, 'body' => 'No'],
        ]);
        $totals = ['measures' => [['type' => 'sum', 'field' => 'seats']], 'groups' => [['field' => 'featured'], ['field' => 'level']]];

        $grouped = $this->search(['scopes' => [['name' => 'commentedTwice']], 'totals' => $totals], 'scoped-shows');
        $joined = $this->search(['scopes' => [['name' => 'commented']], 'totals' => $totals, 'limit' => 10], 'scoped-shows');

        $grouped->assertOk()->assertJsonPath('total', 2);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 2, 'sum_seats' => 400],
            'groups' => [
                ['field' => 'featured', 'buckets' => [['value' => true, 'count' => 2]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'level', 'buckets' => [['value' => 1, 'count' => 1], ['value' => 3, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
            ],
        ], $grouped->json('totals'));
        $joined->assertOk()->assertJsonPath('total', 6);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 6, 'sum_seats' => 1300],
            'groups' => [
                ['field' => 'featured', 'buckets' => [['value' => true, 'count' => 5], ['value' => false, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'level', 'buckets' => [['value' => 3, 'count' => 3], ['value' => 1, 'count' => 2], ['value' => 2, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
            ],
        ], $joined->json('totals'));
    }

    #[Test]
    public function it_leaves_the_totals_key_out_of_a_page_that_does_not_ask_for_it(): void
    {
        $response = $this->search(['limit' => 10]);

        $response->assertOk();
        $this->assertSame(['current_page', 'data', 'from', 'last_page', 'per_page', 'to', 'total', 'meta'], array_keys($response->json()));
    }
}

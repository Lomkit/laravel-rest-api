<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Totals\Stubs\FeaturedRowsExtension;
use Lomkit\Rest\Tests\Feature\Search\Totals\Stubs\PerimeterShowController;
use Lomkit\Rest\Tests\Feature\Search\Totals\Stubs\ScopedShowController;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [FeaturedRowsExtension::class])]
class SqlTotalsRowsTest extends SearchTotalsTestCase
{
    private const array TOTALS = ['measures' => [['type' => 'sum', 'field' => 'seats']], 'groups' => [['field' => 'featured'], ['field' => 'level']]];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(static function (): void {
            Rest::resource('scoped-shows', ScopedShowController::class)->register();
            Rest::resource('perimeter-shows', PerimeterShowController::class)->register();
        });
        DB::table('comments')->insert([
            ['show_id' => 1, 'rating' => 5, 'body' => 'Loved it'],
            ['show_id' => 1, 'rating' => 4, 'body' => 'Good'],
            ['show_id' => 2, 'rating' => 3, 'body' => 'Fine'],
            ['show_id' => 4, 'rating' => 2, 'body' => 'Loud'],
            ['show_id' => 4, 'rating' => 5, 'body' => 'Great'],
            ['show_id' => 4, 'rating' => 1, 'body' => 'No'],
        ]);
    }

    #[Test]
    public function it_counts_a_distinct_joined_scope_as_the_total_does(): void
    {
        DB::table('shows')->insert(array_map(static fn (int $id): array => ['id' => $id, 'title' => 'Late', 'seats' => 10, 'featured' => false, 'status' => 'planned', 'level' => 2, 'starts_on' => '2026-07-01'], range(7, 16)));
        DB::table('comments')->insert(array_merge(...array_map(static fn (int $id): array => [['show_id' => $id, 'rating' => 1, 'body' => 'One'], ['show_id' => $id, 'rating' => 2, 'body' => 'Two']], range(7, 16))));

        $response = $this->search(['scopes' => [['name' => 'distinctlyCommented']], 'totals' => self::TOTALS, 'limit' => 10, 'page' => 1, 'selects' => [['field' => 'id']]], 'scoped-shows');

        $this->assertIdentity($response);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 26, 'sum_seats' => 1500],
            'groups' => [
                ['field' => 'featured', 'buckets' => [['value' => false, 'count' => 21], ['value' => true, 'count' => 5]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'level', 'buckets' => [['value' => 2, 'count' => 21], ['value' => 3, 'count' => 3], ['value' => 1, 'count' => 2]], 'other' => ['count' => 0], 'truncated' => false],
            ],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_counts_a_union_scope_as_the_total_does(): void
    {
        config(['rest.search.select_columns' => false]);

        $response = $this->search(['scopes' => [['name' => 'withHeadliners']], 'filters' => [['field' => 'status', 'value' => 'on_sale']], 'totals' => self::TOTALS, 'limit' => 10], 'scoped-shows');

        $this->assertIdentity($response);
        $this->assertSame(['count' => 4, 'sum_seats' => 700], $response->json('totals.all'));
    }

    #[Test]
    public function it_counts_only_the_rows_that_the_perimeter_and_the_restrictions_keep(): void
    {
        $response = $this->search(['totals' => self::TOTALS], 'perimeter-shows');

        $this->assertIdentity($response);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 2, 'sum_seats' => 150],
            'groups' => [
                ['field' => 'featured', 'buckets' => [['value' => false, 'count' => 2]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'level', 'buckets' => [['value' => 1, 'count' => 2]], 'other' => ['count' => 0], 'truncated' => false],
            ],
        ], $response->json('totals'));
    }

    #[Test]
    #[DataProvider('trashed')]
    public function it_counts_the_trashed_rows_that_the_search_asks_for(string $trashed, int $count, int $seats): void
    {
        $response = $this->search(['trashed' => $trashed, 'totals' => self::TOTALS]);

        $this->assertIdentity($response);
        $this->assertSame(['count' => $count, 'sum_seats' => $seats], $response->json('totals.all'));
    }

    /** @return array<string, array{string, int, int}> */
    public static function trashed(): array
    {
        return [
            'with the trashed rows' => ['with', 6, 1749],
            'only the trashed rows' => ['only', 1, 999],
        ];
    }

    private function assertIdentity(TestResponse $response): void
    {
        $response->assertOk();
        $total = $response->json('total');

        $this->assertSame($total, $response->json('totals.all.count'));

        foreach ($response->json('totals.groups') as $group) {
            $this->assertSame($total, array_sum(array_column($group['buckets'], 'count')) + $group['other']['count']);
        }
    }
}

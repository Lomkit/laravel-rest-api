<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\BulkProductController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PageBoundTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('bulk-products', BulkProductController::class)->register());
        Category::query()->create(['id' => 1, 'name' => 'Lighting']);
        Product::query()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39]);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('pagesPastTheBound')]
    public function it_refuses_a_page_whose_offset_passes_the_largest_integer(string $resource, array $search, int $maxPage): void
    {
        $response = $this->postJson("/api/{$resource}/search", ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => "The search.page field must not be greater than {$maxPage}.",
            'errors' => ['search.page' => ["The search.page field must not be greater than {$maxPage}."]],
        ]);
    }

    /** @return array<string, array{string, array<string, mixed>, int}> */
    public static function pagesPastTheBound(): array
    {
        return [
            'one page past the bound' => ['products', ['page' => 184467440737095518], 184467440737095517],
            'the largest integer' => ['products', ['page' => 9223372036854775807], 184467440737095517],
            'the largest integer with a smaller limit' => ['products', ['page' => 9223372036854775807, 'limit' => 10], 184467440737095517],
            'one page past the bound of larger limits' => ['bulk-products', ['page' => 30744573456182588], 30744573456182587],
        ];
    }

    #[Test]
    #[DataProvider('pagesAtTheBound')]
    public function it_answers_an_empty_page_at_the_bound(string $resource, int $page, int $limit, string $offset): void
    {
        DB::enableQueryLog();

        $response = $this->postJson("/api/{$resource}/search", ['search' => ['page' => $page, 'limit' => $limit]]);

        $response->assertOk()->assertExactJson([
            'current_page' => $page,
            'data' => [],
            'from' => null,
            'last_page' => 1,
            'per_page' => $limit,
            'to' => null,
            'total' => 1,
            'meta' => [],
        ]);
        $this->assertSame([
            "select products.id, products.category_id, products.name, products.price from products order by products.id desc limit {$limit} offset {$offset}",
            'select count(*) as aggregate from products',
        ], array_map(fn (array $query): string => str_replace(['"', '`'], '', $query['query']), DB::getQueryLog()));
    }

    /** @return array<string, array{string, int, int, string}> */
    public static function pagesAtTheBound(): array
    {
        return [
            'the largest limit' => ['products', 184467440737095517, 50, '9223372036854775800'],
            'a smaller limit' => ['products', 184467440737095517, 10, '1844674407370955160'],
            'the largest of larger limits' => ['bulk-products', 30744573456182587, 300, '9223372036854775800'],
        ];
    }
}

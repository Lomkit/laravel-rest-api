<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PaginationValidationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidPages')]
    public function it_rejects_an_invalid_page_or_limit(array $search, array $errors): void
    {
        Product::factory()->create(['name' => 'Desk lamp']);

        $response = $this->postJson('/api/products/search', ['search' => $search]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function invalidPages(): array
    {
        return [
            'a limit the resource does not offer' => [['limit' => 5], [
                'search.limit' => ['The selected search.limit is invalid.'],
            ]],
            'a page that is not a number' => [['page' => 'hello'], [
                'search.page' => ['The search.page field must be an integer.'],
            ]],
            'page zero' => [['page' => 0], [
                'search.page' => ['The search.page field must be at least 1.'],
            ]],
            'a negative page' => [['page' => -1], [
                'search.page' => ['The search.page field must be at least 1.'],
            ]],
        ];
    }

    #[Test]
    public function it_accepts_the_first_page(): void
    {
        $lamp = Product::factory()->create(['name' => 'Desk lamp', 'price' => 39]);

        $response = $this->postJson('/api/products/search', ['search' => ['page' => 1, 'limit' => 10]]);

        $response->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => [
                ['id' => $lamp->id, 'category_id' => $lamp->category_id, 'name' => 'Desk lamp', 'price' => 39],
            ],
            'from' => 1,
            'last_page' => 1,
            'per_page' => 10,
            'to' => 1,
            'total' => 1,
            'meta' => [],
        ]);
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilterValidationTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidFilters')]
    public function it_rejects_an_invalid_filter(array $filters, array $errors): void
    {
        $response = $this->postJson('/api/products/search', ['search' => ['filters' => $filters]]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{list<array<string, mixed>>, array<string, list<string>>}> */
    public static function invalidFilters(): array
    {
        return [
            'an undeclared column' => [[['field' => 'created_at', 'value' => '2026-01-01']], [
                'search.filters.0.field' => ["The 'field' field is not valid."],
            ]],
            'a field that is not a string' => [[['field' => ['name'], 'value' => 'Desk lamp']], [
                'search.filters.0.field' => ['The search.filters.0.field field must be a string.', "The 'field' field is not valid."],
            ]],
            'a nested field that is not a string' => [[['nested' => [['field' => ['name'], 'value' => 'Desk lamp']]]], [
                'search.filters.0.nested.0.field' => ['The search.filters.0.nested.0.field field must be a string.', "The 'field' field is not valid."],
            ]],
        ];
    }
}

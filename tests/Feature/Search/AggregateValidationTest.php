<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AggregateValidationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    /**
     * @param  array<string, mixed>  $aggregate
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidAggregates')]
    public function it_rejects_an_invalid_aggregate(array $aggregate, array $errors): void
    {
        $response = $this->postJson('/api/categories/search', [
            'search' => ['aggregates' => [$aggregate]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function invalidAggregates(): array
    {
        return [
            'an unknown relation' => [['relation' => 'suppliers', 'type' => 'count'], [
                'search.aggregates.0.relation' => ['The relation is not valid or allowed for this resource.'],
            ]],
            'an unknown nested relation' => [['relation' => 'suppliers.country', 'type' => 'count'], [
                'search.aggregates.0.relation' => ['The relation is not valid or allowed for this resource.'],
            ]],
            'a missing type' => [['relation' => 'products'], [
                'search.aggregates.0.type' => ['The search.aggregates.0.type field is required.'],
            ]],
            'an unknown type' => [['relation' => 'products', 'type' => 'median', 'field' => 'price'], [
                'search.aggregates.0.type' => ['The selected search.aggregates.0.type is invalid.'],
            ]],
            'an unknown type with an undeclared field' => [['relation' => 'products', 'type' => 'median', 'field' => 'created_at'], [
                'search.aggregates.0.type' => ['The selected search.aggregates.0.type is invalid.'],
            ]],
            'a missing type with an undeclared field' => [['relation' => 'products', 'field' => 'created_at'], [
                'search.aggregates.0.type' => ['The search.aggregates.0.type field is required.'],
            ]],
            'an undeclared field' => [['relation' => 'products', 'type' => 'min', 'field' => 'created_at'], [
                'search.aggregates.0.field' => ['The selected search.aggregates.0.field is invalid.'],
            ]],
            'a type that needs a field' => [['relation' => 'products', 'type' => 'min'], [
                'search.aggregates.0.field' => ['The search.aggregates.0.field field is required when search.aggregates.0.type is min.'],
            ]],
            'a type that takes no field' => [['relation' => 'products', 'type' => 'exists', 'field' => 'price'], [
                'search.aggregates.0.field' => ['The search.aggregates.0.field field is prohibited when search.aggregates.0.type is exists.'],
            ]],
            'an alias that is not a string' => [['relation' => 'products', 'type' => 'count', 'alias' => ['total']], [
                'search.aggregates.0.alias' => ['The search.aggregates.0.alias field must be a string.'],
            ]],
            'a filter on an undeclared column' => [['relation' => 'products', 'type' => 'count', 'filters' => [['field' => 'created_at', 'value' => '2026-01-01']]], [
                'search.aggregates.0.filters.0.field' => ["The 'field' field is not valid."],
            ]],
            'a filter field that is not a string' => [['relation' => 'products', 'type' => 'count', 'filters' => [['field' => ['name'], 'value' => 'Desk lamp']]], [
                'search.aggregates.0.filters.0.field' => ['The search.aggregates.0.filters.0.field field must be a string.', "The 'field' field is not valid."],
            ]],
        ];
    }

    #[Test]
    public function it_rejects_an_aggregate_filter_nested_deeper_than_the_configured_maximum(): void
    {
        config(['rest.search.max_nesting_depth' => 1]);

        $response = $this->postJson('/api/categories/search', [
            'search' => [
                'aggregates' => [[
                    'relation' => 'products',
                    'type' => 'count',
                    'filters' => [['nested' => [['nested' => [['field' => 'price', 'value' => 39]]]]]],
                ]],
            ],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.aggregates.0.filters.0.nested.0.nested' => ['The search.aggregates.0.filters.0.nested.0.nested field is prohibited.'],
        ]);
    }
}

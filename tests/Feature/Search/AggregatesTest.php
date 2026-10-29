<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AggregatesTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    /**
     * @param  array<string, string>  $aggregate
     * @param  list<float|bool|null>  $values
     */
    #[Test]
    #[DataProvider('aggregates')]
    public function it_aggregates_the_related_rows_of_each_record(array $aggregate, string $key, array $values): void
    {
        $this->seedCategories();

        $response = $this->postJson('/api/categories/search', [
            'search' => ['aggregates' => [['relation' => 'products', ...$aggregate]]],
        ]);

        $response->assertOk();
        $this->assertSame(['id', 'name', $key], array_keys($response->json('data.0')));
        $this->assertSame(['Storage', 'Seating', 'Lighting'], array_column($response->json('data'), 'name'));
        $this->assertSame($values, $this->aggregated($response, $key));
    }

    /**
     * @param  array<string, string>  $aggregate
     * @param  list<float|bool|null>  $values
     */
    #[Test]
    #[DataProvider('aggregates')]
    public function it_names_an_aggregate_after_its_alias(array $aggregate, string $key, array $values): void
    {
        $this->seedCategories();

        $response = $this->postJson('/api/categories/search', [
            'search' => ['aggregates' => [['relation' => 'products', 'alias' => 'summary', ...$aggregate]]],
        ]);

        $response->assertOk();
        $this->assertSame(['id', 'name', 'summary'], array_keys($response->json('data.0')));
        $this->assertSame($values, $this->aggregated($response, 'summary'));
    }

    /** @return array<string, array{array<string, string>, string, list<float|bool|null>}> */
    public static function aggregates(): array
    {
        return [
            'min' => [['type' => 'min', 'field' => 'price'], 'products_min_price', [null, 45.0, 39.0]],
            'max' => [['type' => 'max', 'field' => 'price'], 'products_max_price', [null, 45.0, 129.0]],
            'avg' => [['type' => 'avg', 'field' => 'price'], 'products_avg_price', [null, 45.0, 84.0]],
            'sum' => [['type' => 'sum', 'field' => 'price'], 'products_sum_price', [null, 45.0, 168.0]],
            'count' => [['type' => 'count'], 'products_count', [0.0, 1.0, 2.0]],
            'exists' => [['type' => 'exists'], 'products_exists', [false, true, true]],
        ];
    }

    /**
     * @param  array<string, mixed>  $aggregate
     * @param  list<float|bool|null>  $values
     */
    #[Test]
    #[DataProvider('filteredAggregates')]
    public function it_aggregates_only_the_related_rows_matching_its_filters(array $aggregate, string $key, array $values): void
    {
        $this->seedCategories();

        $response = $this->postJson('/api/categories/search', [
            'search' => ['aggregates' => [['relation' => 'products', ...$aggregate]]],
        ]);

        $response->assertOk();
        $this->assertSame($values, $this->aggregated($response, $key));
    }

    /** @return array<string, array{array<string, mixed>, string, list<float|bool|null>}> */
    public static function filteredAggregates(): array
    {
        $underHundred = ['filters' => [['field' => 'price', 'operator' => '<', 'value' => 100]]];

        return [
            'min' => [['type' => 'min', 'field' => 'price', ...$underHundred], 'products_min_price', [null, 45.0, 39.0]],
            'max' => [['type' => 'max', 'field' => 'price', ...$underHundred], 'products_max_price', [null, 45.0, 39.0]],
            'avg' => [['type' => 'avg', 'field' => 'price', ...$underHundred], 'products_avg_price', [null, 45.0, 39.0]],
            'sum' => [['type' => 'sum', 'field' => 'price', ...$underHundred], 'products_sum_price', [null, 45.0, 39.0]],
            'count' => [['type' => 'count', ...$underHundred], 'products_count', [0.0, 1.0, 1.0]],
            'exists' => [['type' => 'exists', 'filters' => [['field' => 'price', 'operator' => '>', 'value' => 100]]], 'products_exists', [false, false, true]],
            'sum filtered on another field' => [
                ['type' => 'sum', 'field' => 'price', 'filters' => [['field' => 'name', 'operator' => 'like', 'value' => 'Floor%']]],
                'products_sum_price',
                [null, null, 129.0],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $aggregate
     * @param  list<float|bool|null>  $values
     */
    #[Test]
    #[DataProvider('featuredAggregates')]
    public function it_filters_an_aggregate_through_a_pivot_on_a_column_the_pivot_also_has(array $aggregate, string $key, array $values): void
    {
        $this->seedCategories();

        $response = $this->postJson('/api/categories/search', [
            'search' => ['aggregates' => [[
                'relation' => 'featuredProducts',
                'filters' => [['field' => 'price', 'operator' => '<', 'value' => 100]],
                ...$aggregate,
            ]]],
        ]);

        $response->assertOk();
        $this->assertSame($values, $this->aggregated($response, $key));
    }

    /** @return array<string, array{array<string, string>, string, list<float|bool|null>}> */
    public static function featuredAggregates(): array
    {
        return [
            'min' => [['type' => 'min', 'field' => 'price'], 'featured_products_min_price', [39.0, null, null]],
            'max' => [['type' => 'max', 'field' => 'price'], 'featured_products_max_price', [39.0, null, null]],
            'avg' => [['type' => 'avg', 'field' => 'price'], 'featured_products_avg_price', [39.0, null, null]],
            'sum' => [['type' => 'sum', 'field' => 'price'], 'featured_products_sum_price', [39.0, null, null]],
            'count' => [['type' => 'count'], 'featured_products_count', [1.0, 0.0, 0.0]],
            'exists' => [['type' => 'exists'], 'featured_products_exists', [true, false, false]],
        ];
    }

    private function seedCategories(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $seating = Category::factory()->create(['name' => 'Seating']);
        $storage = Category::factory()->create(['name' => 'Storage']);

        $deskLamp = Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        $floorLamp = Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 129]);
        Product::factory()->for($seating)->create(['name' => 'Stool', 'price' => 45]);

        $storage->featuredProducts()->attach([$deskLamp->id => ['price' => 35], $floorLamp->id => ['price' => 99]]);
        $seating->featuredProducts()->attach([$floorLamp->id => ['price' => 119]]);
    }

    /** @return list<float|bool|null> */
    private function aggregated(TestResponse $response, string $key): array
    {
        return array_map(
            fn (mixed $value): float|bool|null => is_numeric($value) ? (float) $value : $value,
            array_column($response->json('data'), $key),
        );
    }
}

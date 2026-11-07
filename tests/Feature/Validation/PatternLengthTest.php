<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Closure;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\RowCount;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;
use Lomkit\Rest\Tests\Fixtures\Sales\Sales;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PatternLengthTest extends PackageTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        MetricOrderResource::$declared = [RowCount::make()];
        Rest::resource('metric-orders', MetricOrderController::class)->register();
        Author::factory()->create(['id' => 1]);
        Post::factory()->create(['id' => 1, 'author_id' => 1]);
        Category::factory()->create(['id' => 1]);
        Product::factory()->create(['id' => 1, 'category_id' => 1]);
        Order::create(['id' => 1, 'team_id' => 1, 'reference' => 'A-1001', 'status' => 'paid', 'channel' => 'web', 'price' => '20.00', 'quantity' => 2, 'gift' => false, 'placed_at' => '2026-11-01 09:00:00']);
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog, new Sales];
    }

    /** @param Closure(string): array<string, mixed> $search */
    #[Test]
    #[DataProvider('pathFamilies')]
    public function it_refuses_a_pattern_one_character_over_the_limit(string $uri, Closure $search, string $key): void
    {
        $message = "The {$key} field must not be greater than 255 characters.";

        $response = $this->postJson($uri, ['search' => $search(str_repeat('R', 255).'%')]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @param Closure(string): array<string, mixed> $search */
    #[Test]
    #[DataProvider('pathFamilies')]
    public function it_accepts_a_pattern_at_the_limit(string $uri, Closure $search, string $key): void
    {
        $response = $this->postJson($uri, ['search' => $search(str_repeat('R', 254).'%')]);

        $response->assertOk()->assertJsonMissingValidationErrors($key);
    }

    /** @return array<string, array{string, Closure(string): array<string, mixed>, string}> */
    public static function pathFamilies(): array
    {
        $like = static fn (string $field, string $value): array => ['field' => $field, 'operator' => 'like', 'value' => $value];

        return [
            'a filter' => ['/api/posts/search', static fn (string $value): array => [
                'filters' => [$like('title', $value)],
            ], 'search.filters.0.value'],
            'a nested filter' => ['/api/posts/search', static fn (string $value): array => [
                'filters' => [['nested' => [$like('title', $value)]]],
            ], 'search.filters.0.nested.0.value'],
            'a relation path' => ['/api/posts/search', static fn (string $value): array => [
                'filters' => [$like('author.first_name', $value)],
            ], 'search.filters.0.value'],
            'a relation group' => ['/api/posts/search', static fn (string $value): array => [
                'filters' => [['relation' => 'author', 'nested' => [$like('first_name', $value)]]],
            ], 'search.filters.0.nested.0.value'],
            'an include filter' => ['/api/posts/search', static fn (string $value): array => [
                'includes' => [['relation' => 'author', 'filters' => [$like('last_name', $value)]]],
            ], 'search.includes.0.filters.0.value'],
            'an aggregate filter' => ['/api/categories/search', static fn (string $value): array => [
                'aggregates' => [['relation' => 'products', 'type' => 'count', 'filters' => [$like('name', $value)]]],
            ], 'search.aggregates.0.filters.0.value'],
            'the search of an action' => ['/api/posts/actions/publish-posts', static fn (string $value): array => [
                'filters' => [$like('title', $value)],
            ], 'search.filters.0.value'],
            'the search of a metric' => ['/metric-orders/metrics/row-count', static fn (string $value): array => [
                'filters' => [$like('reference', $value)],
            ], 'search.filters.0.value'],
        ];
    }

    #[Test]
    #[DataProvider('operators')]
    public function it_refuses_each_pattern_operator_over_the_limit(string $operator): void
    {
        $message = 'The search.filters.0.value field must not be greater than 255 characters.';

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['field' => 'title', 'operator' => $operator, 'value' => str_repeat('R', 255).'%'],
        ]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.value' => [$message]]]);
    }

    /** @return array<string, array{string}> */
    public static function operators(): array
    {
        return [
            'like' => ['like'],
            'not like' => ['not like'],
            'ilike' => ['ilike'],
            'not ilike' => ['not ilike'],
        ];
    }

    #[Test]
    public function it_refuses_a_pattern_of_a_hundred_thousand_characters(): void
    {
        $message = 'The search.filters.0.value field must not be greater than 255 characters.';

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['field' => 'title', 'operator' => 'like', 'value' => str_repeat('%_', 50000)],
        ]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.value' => [$message]]]);
    }

    #[Test]
    public function it_reads_the_pattern_length_from_the_config(): void
    {
        config(['rest.limits.lengths.patterns' => 8]);
        $message = 'The search.filters.0.value field must not be greater than 8 characters.';

        $over = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => 'Releases%']]]]);
        $at = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => 'Release%']]]]);

        $over->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.value' => [$message]]]);
        $at->assertOk();
    }
}

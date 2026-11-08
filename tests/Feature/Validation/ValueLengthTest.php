<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Closure;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\RowCount;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;
use Lomkit\Rest\Tests\Fixtures\Sales\Sales;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ValueLengthTest extends PackageTestCase
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
    public function it_refuses_a_value_one_character_over_the_limit(string $uri, Closure $search, string $key): void
    {
        $message = "The {$key} field must not be greater than 1000 characters.";

        $response = $this->postJson($uri, ['search' => $search(str_repeat('R', 1001))]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @param Closure(string): array<string, mixed> $search */
    #[Test]
    #[DataProvider('pathFamilies')]
    public function it_accepts_a_value_at_the_limit(string $uri, Closure $search, string $key): void
    {
        $response = $this->postJson($uri, ['search' => $search(str_repeat('R', 1000))]);

        $response->assertOk()->assertJsonMissingValidationErrors($key);
    }

    /** @return array<string, array{string, Closure(string): array<string, mixed>, string}> */
    public static function pathFamilies(): array
    {
        $equal = static fn (string $field, string $value): array => ['field' => $field, 'value' => $value];

        return [
            'a filter' => ['/api/posts/search', static fn (string $value): array => [
                'filters' => [$equal('title', $value)],
            ], 'search.filters.0.value'],
            'a nested filter' => ['/api/posts/search', static fn (string $value): array => [
                'filters' => [['nested' => [$equal('title', $value)]]],
            ], 'search.filters.0.nested.0.value'],
            'a relation path' => ['/api/posts/search', static fn (string $value): array => [
                'filters' => [$equal('author.first_name', $value)],
            ], 'search.filters.0.value'],
            'a relation group' => ['/api/posts/search', static fn (string $value): array => [
                'filters' => [['relation' => 'author', 'nested' => [$equal('first_name', $value)]]],
            ], 'search.filters.0.nested.0.value'],
            'an include filter' => ['/api/posts/search', static fn (string $value): array => [
                'includes' => [['relation' => 'author', 'filters' => [$equal('last_name', $value)]]],
            ], 'search.includes.0.filters.0.value'],
            'an aggregate filter' => ['/api/categories/search', static fn (string $value): array => [
                'aggregates' => [['relation' => 'products', 'type' => 'count', 'filters' => [$equal('name', $value)]]],
            ], 'search.aggregates.0.filters.0.value'],
            'the search of an action' => ['/api/posts/actions/publish-posts', static fn (string $value): array => [
                'filters' => [$equal('title', $value)],
            ], 'search.filters.0.value'],
            'the search of a metric' => ['/metric-orders/metrics/row-count', static fn (string $value): array => [
                'filters' => [$equal('reference', $value)],
            ], 'search.filters.0.value'],
        ];
    }

    /** @param array<string, mixed> $filter */
    #[Test]
    #[DataProvider('operators')]
    public function it_refuses_a_value_over_the_limit_for_each_operator_that_takes_one(array $filter, string $key): void
    {
        $message = "The {$key} field must not be greater than 1000 characters.";

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [$filter]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function operators(): array
    {
        $long = str_repeat('R', 1001);

        return [
            'equal' => [['field' => 'title', 'operator' => '=', 'value' => $long], 'search.filters.0.value'],
            'not equal' => [['field' => 'title', 'operator' => '!=', 'value' => $long], 'search.filters.0.value'],
            'an item of in' => [['field' => 'title', 'operator' => 'in', 'value' => ['Release', $long]], 'search.filters.0.value.1'],
            'an item of not in' => [['field' => 'title', 'operator' => 'not in', 'value' => [$long]], 'search.filters.0.value.0'],
            'a numeric string of between' => [['field' => 'rating', 'operator' => 'between', 'value' => ['1', '1'.str_repeat('0', 1000)]], 'search.filters.0.value.1'],
            'a numeric string of greater than' => [['field' => 'rating', 'operator' => '>', 'value' => '1'.str_repeat('0', 1000)], 'search.filters.0.value'],
        ];
    }

    #[Test]
    public function it_refuses_a_value_of_a_million_characters(): void
    {
        $message = 'The search.filters.0.value field must not be greater than 1000 characters.';

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['field' => 'title', 'value' => str_repeat('R', 1000000)],
        ]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.value' => [$message]]]);
    }

    #[Test]
    public function it_reads_the_value_length_from_the_config(): void
    {
        config(['rest.limits.lengths.values' => 8]);
        $message = 'The search.filters.0.value field must not be greater than 8 characters.';

        $over = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'title', 'value' => 'Releases1']]]]);
        $at = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'title', 'value' => 'Releases']]]]);

        $over->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.value' => [$message]]]);
        $at->assertOk();
    }

    #[Test]
    public function it_counts_the_characters_of_a_multibyte_value(): void
    {
        config(['rest.limits.lengths.values' => 8]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'title', 'value' => 'éééééééé']]]]);

        $response->assertOk();
    }

    /** @param array{patterns: int, values: int} $lengths */
    #[Test]
    #[DataProvider('patternLengths')]
    public function it_bounds_a_pattern_by_the_smaller_of_the_two_lengths(array $lengths, string $over, string $at, int $max): void
    {
        config(['rest.limits.lengths.patterns' => $lengths['patterns'], 'rest.limits.lengths.values' => $lengths['values']]);
        $message = "The search.filters.0.value field must not be greater than {$max} characters.";

        $refused = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => $over]]]]);
        $accepted = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => $at]]]]);

        $refused->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.value' => [$message]]]);
        $accepted->assertOk();
    }

    /** @return array<string, array{array{patterns: int, values: int}, string, string, int}> */
    public static function patternLengths(): array
    {
        return [
            'values smaller' => [['patterns' => 255, 'values' => 8], 'Release%%', 'Release%', 8],
            'patterns smaller' => [['patterns' => 5, 'values' => 8], 'Relea%', 'Rele%', 5],
        ];
    }

    #[Test]
    public function it_refuses_a_value_over_the_limit_in_a_search_run_outside_http(): void
    {
        $message = 'The search.filters.0.value field must not be greater than 1000 characters.';

        try {
            Rest::search(PostResource::class)->as(null)->payload(['filters' => [['field' => 'title', 'value' => str_repeat('R', 1001)]]])->render();
            $this->fail('The search ran.');
        } catch (ValidationException $exception) {
            $this->assertSame(['search.filters.0.value' => [$message]], $exception->errors());
        }
    }
}

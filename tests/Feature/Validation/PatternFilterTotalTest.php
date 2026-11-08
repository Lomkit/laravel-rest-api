<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\RowCount;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Sales\Sales;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PatternFilterTotalTest extends PackageTestCase
{
    private const array LIKE = ['field' => 'title', 'operator' => 'like', 'value' => 'En%'];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Sales];
    }

    #[Test]
    public function it_reads_ten_pattern_filters_by_default(): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => array_fill(0, 11, self::LIKE)]]);

        $this->assertRefused($response, 'search.filters', 'The search.filters field goes over the limit of 10 pattern filters in one search.');
    }

    #[Test]
    public function it_runs_a_search_at_the_limit(): void
    {
        config(['rest.limits.totals.patterns' => 4]);
        $ada = Author::factory()->create(['id' => 1]);
        $alan = Author::factory()->create(['id' => 2, 'first_name' => 'Alan', 'last_name' => 'Turing']);
        Post::factory()->for($ada)->create(['id' => 1, 'title' => 'Engines', 'views' => 10]);
        Post::factory()->for($ada)->create(['id' => 2, 'title' => 'Engine notes', 'views' => 20]);
        Post::factory()->for($alan)->create(['id' => 3, 'title' => 'Engines of logic', 'views' => 10]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            self::LIKE,
            ['field' => 'author.first_name', 'operator' => 'ilike', 'value' => 'ad%'],
            ['field' => 'views', 'value' => 10],
            ['nested' => [['field' => 'title', 'operator' => 'not like', 'value' => '%notes']]],
            ['relation' => 'author', 'nested' => [['field' => 'last_name', 'operator' => 'not ilike', 'value' => 'tur%']]],
        ]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1]);
    }

    #[Test]
    public function it_counts_the_pattern_filters_of_nested_groups(): void
    {
        config(['rest.limits.totals.patterns' => 2]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            self::LIKE,
            ['nested' => [['field' => 'title', 'value' => 'Engines'], self::LIKE]],
            ['field' => 'title', 'operator' => 'ilike', 'value' => 'en%', 'type' => 'or'],
        ]]]);

        $this->assertRefused($response, 'search.filters', 'The search.filters field goes over the limit of 2 pattern filters in one search.');
    }

    #[Test]
    public function it_counts_the_pattern_filters_inside_a_relation_group(): void
    {
        config(['rest.limits.totals.patterns' => 1]);

        $response = $this->postJson('/api/authors/search', ['search' => ['filters' => [
            ['field' => 'first_name', 'operator' => 'like', 'value' => 'A%'],
            ['relation' => 'posts', 'nested' => [self::LIKE]],
        ]]]);

        $this->assertRefused($response, 'search.filters', 'The search.filters field goes over the limit of 1 pattern filters in one search.');
    }

    #[Test]
    public function it_counts_the_pattern_filters_of_includes(): void
    {
        config(['rest.limits.totals.patterns' => 2]);

        $response = $this->postJson('/api/posts/search', ['search' => [
            'filters' => [self::LIKE],
            'includes' => [['relation' => 'author', 'filters' => [
                ['field' => 'first_name', 'operator' => 'like', 'value' => 'A%'],
                ['field' => 'last_name', 'operator' => 'not like', 'value' => 'T%'],
            ]]],
        ]]);

        $this->assertRefused($response, 'search.includes.0.filters', 'The search.includes.0.filters field goes over the limit of 2 pattern filters in one search.');
    }

    #[Test]
    public function it_counts_the_pattern_filters_of_aggregates(): void
    {
        config(['rest.limits.totals.patterns' => 1]);

        $response = $this->postJson('/api/authors/search', ['search' => [
            'filters' => [['field' => 'first_name', 'operator' => 'like', 'value' => 'A%']],
            'aggregates' => [[
                'relation' => 'posts',
                'type' => 'count',
                'filters' => [self::LIKE],
            ]],
        ]]);

        $this->assertRefused($response, 'search.aggregates.0.filters', 'The search.aggregates.0.filters field goes over the limit of 1 pattern filters in one search.');
    }

    #[Test]
    public function it_counts_the_pattern_filters_of_the_search_of_an_action(): void
    {
        config(['rest.limits.totals.patterns' => 1]);

        $response = $this->postJson('/api/posts/actions/publish-posts', ['search' => ['filters' => [self::LIKE, self::LIKE]]]);

        $this->assertRefused($response, 'search.filters', 'The search.filters field goes over the limit of 1 pattern filters in one search.');
    }

    #[Test]
    public function it_counts_the_pattern_filters_of_the_search_of_a_metric(): void
    {
        config(['rest.limits.totals.patterns' => 1]);
        MetricOrderResource::$declared = [RowCount::make()];
        Rest::resource('metric-orders', MetricOrderController::class)->register();
        $like = ['field' => 'reference', 'operator' => 'like', 'value' => 'A-%'];

        $response = $this->postJson('/metric-orders/metrics/row-count', ['search' => ['filters' => [$like, $like]]]);

        $this->assertRefused($response, 'search.filters', 'The search.filters field goes over the limit of 1 pattern filters in one search.');
    }

    #[Test]
    public function it_reports_the_pattern_filter_total_once(): void
    {
        config(['rest.limits.totals.patterns' => 1]);

        $response = $this->postJson('/api/posts/search', ['search' => [
            'filters' => [self::LIKE, self::LIKE],
            'includes' => [['relation' => 'author', 'filters' => [
                ['field' => 'first_name', 'operator' => 'like', 'value' => 'A%'],
                ['field' => 'last_name', 'operator' => 'like', 'value' => 'L%'],
            ]]],
        ]]);

        $this->assertRefused($response, 'search.filters', 'The search.filters field goes over the limit of 1 pattern filters in one search.');
    }

    #[Test]
    public function it_counts_the_pattern_filters_of_a_search_run_outside_http(): void
    {
        config(['rest.limits.totals.patterns' => 1]);

        try {
            Rest::search(PostResource::class)
                ->as(null)
                ->payload(['filters' => [self::LIKE, self::LIKE]])
                ->render();
            $this->fail('The search ran.');
        } catch (ValidationException $exception) {
            $this->assertSame(['search.filters' => ['The search.filters field goes over the limit of 1 pattern filters in one search.']], $exception->errors());
        }
    }

    private function assertRefused(TestResponse $response, string $key, string $message): void
    {
        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }
}

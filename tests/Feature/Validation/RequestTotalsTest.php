<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RequestTotalsTest extends PackageTestCase
{
    private const array FILTER = ['field' => 'views', 'value' => 1];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[Test]
    public function it_refuses_the_filter_list_that_goes_over_the_total(): void
    {
        config(['rest.limits.totals.filters' => 4]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['nested' => [self::FILTER, self::FILTER, self::FILTER]],
            self::FILTER,
        ]]]);

        $this->assertRefused($response, 'search.filters.0.nested', 'The search.filters.0.nested field goes over the limit of 4 filters in one search.');
    }

    #[Test]
    public function it_reports_the_filter_total_once(): void
    {
        config(['rest.limits.totals.filters' => 3]);
        $ada = ['field' => 'first_name', 'value' => 'Ada'];

        $response = $this->postJson('/api/posts/search', ['search' => [
            'filters' => [self::FILTER, self::FILTER],
            'includes' => [
                ['relation' => 'author', 'filters' => [$ada, $ada]],
                ['relation' => 'author', 'alias' => 'writer', 'filters' => [$ada, $ada]],
            ],
        ]]);

        $this->assertRefused($response, 'search.includes.0.filters', 'The search.includes.0.filters field goes over the limit of 3 filters in one search.');
    }

    #[Test]
    public function it_counts_the_filters_of_aggregates(): void
    {
        config(['rest.limits.totals.filters' => 2]);

        $response = $this->postJson('/api/categories/search', ['search' => [
            'filters' => [['field' => 'name', 'value' => 'Lighting']],
            'aggregates' => [[
                'relation' => 'products',
                'type' => 'count',
                'filters' => [['field' => 'price', 'value' => 39], ['field' => 'price', 'value' => 129]],
            ]],
        ]]);

        $this->assertRefused($response, 'search.aggregates.0.filters', 'The search.aggregates.0.filters field goes over the limit of 2 filters in one search.');
    }

    #[Test]
    public function it_refuses_the_list_that_goes_over_the_total_of_entries(): void
    {
        config(['rest.limits.totals.entries' => 5]);

        $response = $this->postJson('/api/posts/search', ['search' => [
            'sorts' => [['field' => 'views'], ['field' => 'title'], ['field' => 'id']],
            'selects' => [['field' => 'title'], ['field' => 'views'], ['field' => 'id']],
        ]]);

        $this->assertRefused($response, 'search.selects', 'The search.selects field goes over the limit of 5 list entries in one request.');
    }

    #[Test]
    public function it_counts_the_values_of_a_filter(): void
    {
        config(['rest.limits.totals.entries' => 3]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['field' => 'views', 'operator' => 'in', 'value' => [1, 2, 3]],
        ]]]);

        $this->assertRefused($response, 'search.filters.0.value', 'The search.filters.0.value field goes over the limit of 3 list entries in one request.');
    }

    #[Test]
    public function it_counts_the_entries_of_relations(): void
    {
        config(['rest.limits.totals.entries' => 4]);
        $product = ['operation' => 'create', 'attributes' => ['name' => 'Desk lamp', 'price' => 39]];
        $category = ['operation' => 'create', 'attributes' => ['name' => 'Lighting'], 'relations' => ['products' => [$product, $product]]];

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [$category, $category]]);

        $this->assertRefused($response, 'mutate.1.relations.products', 'The mutate.1.relations.products field goes over the limit of 4 list entries in one request.');
        $this->assertSame(0, Category::count());
        $this->assertSame(0, Product::count());
    }

    #[Test]
    public function it_counts_the_input_fields(): void
    {
        config(['rest.limits.totals.entries' => 2]);

        $response = $this->postJson('/api/posts/search', ['search' => ['instructions' => [[
            'name' => 'highlight-posts',
            'fields' => [['name' => 'featured', 'value' => true], ['name' => 'min_views', 'value' => 'many']],
        ]]]]);

        $this->assertRefused($response, 'search.instructions.0.fields', 'The search.instructions.0.fields field goes over the limit of 2 list entries in one request.');
    }

    #[Test]
    public function it_counts_the_action_fields_after_the_search_lists(): void
    {
        config(['rest.limits.totals.entries' => 2]);

        $response = $this->postJson('/api/posts/actions/publish-posts', [
            'search' => ['sorts' => [['field' => 'views'], ['field' => 'title']]],
            'fields' => [['name' => 'notify', 'value' => true]],
        ]);

        $this->assertRefused($response, 'fields', 'The fields field goes over the limit of 2 list entries in one request.');
    }

    private function assertRefused(TestResponse $response, string $key, string $message): void
    {
        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }
}

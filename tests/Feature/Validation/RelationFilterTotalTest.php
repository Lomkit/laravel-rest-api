<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RelationFilterTotalTest extends PackageTestCase
{
    private const array ADA = ['field' => 'author.first_name', 'value' => 'Ada'];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_reads_twenty_relation_filters_by_default(): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => array_fill(0, 21, self::ADA)]]);

        $this->assertRefused($response, 'search.filters', 'The search.filters field goes over the limit of 20 relation filters in one search.');
    }

    #[Test]
    public function it_runs_a_search_at_the_limit(): void
    {
        config(['rest.limits.totals.relations' => 2]);
        $ada = Author::factory()->create(['id' => 1]);
        $alan = Author::factory()->create(['id' => 2, 'first_name' => 'Alan', 'last_name' => 'Turing']);
        Post::factory()->for($ada)->create(['id' => 1, 'title' => 'Engines', 'views' => 10]);
        Post::factory()->for($ada)->create(['id' => 2, 'title' => 'Notes', 'views' => 20]);
        Post::factory()->for($alan)->create(['id' => 3, 'title' => 'Machines', 'views' => 10]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            self::ADA,
            ['relation' => 'author', 'nested' => [['field' => 'last_name', 'value' => 'Lovelace']]],
            ['field' => 'views', 'value' => 10],
            ['nested' => [['field' => 'title', 'value' => 'Engines']]],
        ]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1]);
    }

    #[Test]
    public function it_counts_the_relation_filters_of_nested_groups(): void
    {
        config(['rest.limits.totals.relations' => 2]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            self::ADA,
            ['nested' => [['field' => 'title', 'value' => 'Engines'], self::ADA]],
            ['relation' => 'author'],
        ]]]);

        $this->assertRefused($response, 'search.filters', 'The search.filters field goes over the limit of 2 relation filters in one search.');
    }

    #[Test]
    public function it_counts_the_relation_filters_inside_a_relation_group(): void
    {
        config(['rest.limits.totals.relations' => 1]);

        $response = $this->postJson('/api/authors/search', ['search' => ['filters' => [
            ['relation' => 'posts', 'nested' => [['field' => 'author.last_name', 'value' => 'Lovelace']]],
        ]]]);

        $this->assertRefused($response, 'search.filters', 'The search.filters field goes over the limit of 1 relation filters in one search.');
    }

    #[Test]
    public function it_counts_the_relation_filters_of_includes(): void
    {
        config(['rest.limits.totals.relations' => 2]);

        $response = $this->postJson('/api/posts/search', ['search' => [
            'filters' => [self::ADA],
            'includes' => [['relation' => 'author', 'filters' => [
                ['field' => 'posts.title', 'value' => 'Engines'],
                ['relation' => 'tags'],
            ]]],
        ]]);

        $this->assertRefused($response, 'search.includes.0.filters', 'The search.includes.0.filters field goes over the limit of 2 relation filters in one search.');
    }

    #[Test]
    public function it_counts_the_relation_filters_of_aggregates(): void
    {
        config(['rest.limits.totals.relations' => 1]);

        $response = $this->postJson('/api/authors/search', ['search' => [
            'filters' => [['field' => 'posts.title', 'value' => 'Engines']],
            'aggregates' => [[
                'relation' => 'posts',
                'type' => 'count',
                'filters' => [['field' => 'author.first_name', 'value' => 'Ada']],
            ]],
        ]]);

        $this->assertRefused($response, 'search.aggregates.0.filters', 'The search.aggregates.0.filters field goes over the limit of 1 relation filters in one search.');
    }

    #[Test]
    public function it_reports_the_relation_filter_total_once(): void
    {
        config(['rest.limits.totals.relations' => 1]);

        $response = $this->postJson('/api/posts/search', ['search' => [
            'filters' => [self::ADA, self::ADA],
            'includes' => [['relation' => 'author', 'filters' => [
                ['field' => 'posts.title', 'value' => 'Engines'],
                ['field' => 'posts.title', 'value' => 'Notes'],
            ]]],
        ]]);

        $this->assertRefused($response, 'search.filters', 'The search.filters field goes over the limit of 1 relation filters in one search.');
    }

    private function assertRefused(TestResponse $response, string $key, string $message): void
    {
        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }
}

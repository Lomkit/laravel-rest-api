<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\FilterableUnselectableAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\FilterableUnselectablePostController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnselectableAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnselectablePostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnselectableFieldsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('unselectable-posts', UnselectablePostController::class)->register();
        Rest::resource('unselectable-authors', UnselectableAuthorController::class)->register();
        Rest::resource('filterable-unselectable-posts', FilterableUnselectablePostController::class)->register();
        Rest::resource('filterable-unselectable-authors', FilterableUnselectableAuthorController::class)->register();

        Author::factory()->create(['id' => 1, 'first_name' => 'Ada', 'created_at' => '2026-01-01 09:00:00']);
        Author::factory()->create(['id' => 2, 'first_name' => 'Grace', 'created_at' => '2026-01-02 09:00:00']);
        Post::factory()->create(['id' => 1, 'author_id' => 1, 'title' => 'Engines', 'views' => 150]);
        Post::factory()->create(['id' => 2, 'author_id' => 1, 'title' => 'Notes', 'views' => 10]);
        Post::factory()->create(['id' => 3, 'author_id' => 2, 'title' => 'Compilers', 'views' => 20]);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('refusedSearches')]
    public function it_refuses_to_filter_sort_or_aggregate_a_field_that_cannot_be_selected(string $resource, array $search, string $key): void
    {
        $response = $this->postJson("/unselectable-{$resource}/search", ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => "The selected {$key} is invalid.",
            'errors' => [$key => ["The selected {$key} is invalid."]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<array<string, mixed>>  $data
     */
    #[Test]
    #[DataProvider('acceptedSearches')]
    public function it_filters_sorts_and_aggregates_a_field_that_cannot_be_selected_when_declared_filterable_and_sortable(string $resource, array $search, array $data): void
    {
        $response = $this->postJson("/filterable-unselectable-{$resource}/search", ['search' => [...$search, 'selects' => [['field' => 'id']]]]);

        $response->assertOk();
        $this->assertSame(self::numbers($data), self::numbers($response->json('data')));
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function refusedSearches(): array
    {
        return array_map(fn (array $case): array => [$case[0], $case[1], $case[2]], self::readingSearches());
    }

    /** @return array<string, array{string, array<string, mixed>, list<array<string, mixed>>}> */
    public static function acceptedSearches(): array
    {
        return array_map(fn (array $case): array => [$case[0], $case[1], $case[3]], self::readingSearches());
    }

    /** @return array<string, array{string, array<string, mixed>, string, list<array<string, mixed>>}> */
    private static function readingSearches(): array
    {
        $popular = [['field' => 'views', 'operator' => '>', 'value' => 50]];
        $byId = [['field' => 'id']];

        return [
            'a filter' => ['posts', ['filters' => $popular], 'search.filters.0.field', [['id' => 1]]],
            'a nested filter' => ['posts', ['filters' => [['nested' => $popular]]], 'search.filters.0.nested.0.field', [['id' => 1]]],
            'a filter through a to-many relation' => ['authors', ['filters' => [['field' => 'posts.views', 'operator' => '>', 'value' => 50]]], 'search.filters.0.field', [['id' => 1]]],
            'a filter through a to-one relation' => ['posts', ['filters' => [['field' => 'author.created_at', 'operator' => '>', 'value' => '2026-01-01 12:00:00']]], 'search.filters.0.field', [['id' => 3]]],
            'a relation group' => ['authors', ['filters' => [['relation' => 'posts', 'nested' => $popular]]], 'search.filters.0.nested.0.field', [['id' => 1]]],
            'an include filter' => ['authors', ['includes' => [['relation' => 'posts', 'selects' => [['field' => 'id']], 'filters' => $popular]], 'sorts' => $byId], 'search.includes.0.filters.0.field', [['id' => 1, 'posts' => [['id' => 1]]], ['id' => 2, 'posts' => []]]],
            'an aggregate filter' => ['authors', ['aggregates' => [['relation' => 'posts', 'type' => 'count', 'filters' => $popular]], 'sorts' => $byId], 'search.aggregates.0.filters.0.field', [['id' => 1, 'posts_count' => 1], ['id' => 2, 'posts_count' => 0]]],
            'a sort' => ['posts', ['sorts' => [['field' => 'views']]], 'search.sorts.0.field', [['id' => 2], ['id' => 3], ['id' => 1]]],
            'a sort through a to-one relation' => ['posts', ['sorts' => [['field' => 'author.created_at', 'direction' => 'desc'], ['field' => 'id']]], 'search.sorts.0.field', [['id' => 3], ['id' => 1], ['id' => 2]]],
            'an include sort' => ['authors', ['includes' => [['relation' => 'posts', 'selects' => [['field' => 'id']], 'sorts' => [['field' => 'views']]]], 'sorts' => $byId], 'search.includes.0.sorts.0.field', [['id' => 1, 'posts' => [['id' => 2], ['id' => 1]]], ['id' => 2, 'posts' => [['id' => 3]]]]],
            'a sum' => ['authors', ['aggregates' => [['relation' => 'posts', 'type' => 'sum', 'field' => 'views']], 'sorts' => $byId], 'search.aggregates.0.field', [['id' => 1, 'posts_sum_views' => 160], ['id' => 2, 'posts_sum_views' => 20]]],
            'an average' => ['authors', ['aggregates' => [['relation' => 'posts', 'type' => 'avg', 'field' => 'views']], 'sorts' => $byId], 'search.aggregates.0.field', [['id' => 1, 'posts_avg_views' => 80], ['id' => 2, 'posts_avg_views' => 20]]],
            'a minimum' => ['authors', ['aggregates' => [['relation' => 'posts', 'type' => 'min', 'field' => 'views']], 'sorts' => $byId], 'search.aggregates.0.field', [['id' => 1, 'posts_min_views' => 10], ['id' => 2, 'posts_min_views' => 20]]],
            'a maximum' => ['authors', ['aggregates' => [['relation' => 'posts', 'type' => 'max', 'field' => 'views']], 'sorts' => $byId], 'search.aggregates.0.field', [['id' => 1, 'posts_max_views' => 150], ['id' => 2, 'posts_max_views' => 20]]],
            'a maximum through a to-one relation' => ['posts', ['aggregates' => [['relation' => 'author', 'type' => 'max', 'field' => 'created_at']], 'sorts' => $byId], 'search.aggregates.0.field', [['id' => 1, 'author_max_created_at' => '2026-01-01 09:00:00'], ['id' => 2, 'author_max_created_at' => '2026-01-01 09:00:00'], ['id' => 3, 'author_max_created_at' => '2026-01-02 09:00:00']]],
            'an include aggregate' => ['authors', ['includes' => [['relation' => 'posts', 'selects' => [['field' => 'id']], 'aggregates' => [['relation' => 'author', 'type' => 'min', 'field' => 'created_at']], 'sorts' => $byId]], 'sorts' => $byId], 'search.includes.0.aggregates.0.field', [['id' => 1, 'posts' => [['id' => 1, 'author_min_created_at' => '2026-01-01 09:00:00'], ['id' => 2, 'author_min_created_at' => '2026-01-01 09:00:00']]], ['id' => 2, 'posts' => [['id' => 3, 'author_min_created_at' => '2026-01-02 09:00:00']]]]],
        ];
    }

    #[Test]
    public function it_counts_and_checks_the_rows_of_a_relation_whose_fields_cannot_be_selected(): void
    {
        $response = $this->postJson('/unselectable-authors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'aggregates' => [['relation' => 'posts', 'type' => 'count'], ['relation' => 'posts', 'type' => 'exists']],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'posts_count' => 2, 'posts_exists' => true],
            ['id' => 2, 'posts_count' => 1, 'posts_exists' => true],
        ]);
    }

    #[Test]
    public function it_returns_no_field_that_cannot_be_selected_when_declared_filterable_and_sortable(): void
    {
        $response = $this->postJson('/filterable-unselectable-posts/search', ['search' => ['sorts' => [['field' => 'views']]]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 2, 'title' => 'Notes'],
            ['id' => 3, 'title' => 'Compilers'],
            ['id' => 1, 'title' => 'Engines'],
        ]);
    }

    private static function numbers(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => array_map(self::numbers(...), $value),
            is_numeric($value) => (float) $value,
            default => $value,
        };
    }
}

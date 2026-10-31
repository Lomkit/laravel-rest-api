<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PatternAuthorController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilterValuesTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidPostFilters')]
    public function it_rejects_a_filter_whose_operator_or_value_does_not_fit_the_field(array $search, array $errors): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => $search]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function invalidPostFilters(): array
    {
        return [
            'equal to null' => [['filters' => [['field' => 'summary', 'value' => null]]], [
                'search.filters.0.value' => ['The search.filters.0.value field is required.'],
            ]],
            'not equal to null' => [['filters' => [['field' => 'summary', 'operator' => '!=', 'value' => null]]], [
                'search.filters.0.value' => ['The search.filters.0.value field is required.'],
            ]],
            'equal without a value' => [['filters' => [['field' => 'views']]], [
                'search.filters.0.value' => ['The search.filters.0.value field is required.'],
            ]],
            'is null on a field that is not nullable' => [['filters' => [['field' => 'title', 'operator' => 'is null']]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'is null with a value' => [['filters' => [['field' => 'summary', 'operator' => 'is null', 'value' => 'Draft']]], [
                'search.filters.0.value' => ['The search.filters.0.value field is prohibited.'],
            ]],
            'is not null with a value' => [['filters' => [['field' => 'rating', 'operator' => 'is not null', 'value' => 4]]], [
                'search.filters.0.value' => ['The search.filters.0.value field is prohibited.'],
            ]],
            'like on an integer' => [['filters' => [['field' => 'views', 'operator' => 'like', 'value' => '1%']]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'between on a text' => [['filters' => [['field' => 'title', 'operator' => 'between', 'value' => ['A', 'B']]]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'in on a boolean' => [['filters' => [['field' => 'featured', 'operator' => 'in', 'value' => [true]]]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'greater than on an enum' => [['filters' => [['field' => 'status', 'operator' => '>', 'value' => 'draft']]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'an unknown operator' => [['filters' => [['field' => 'title', 'operator' => 'contains', 'value' => 'report']]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'an unknown operator on an unknown field' => [['filters' => [['field' => 'subtitle', 'operator' => 'contains', 'value' => 'report']]], [
                'search.filters.0.field' => ['The selected search.filters.0.field is invalid.'],
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'in with an empty list' => [['filters' => [['field' => 'views', 'operator' => 'in', 'value' => []]]], [
                'search.filters.0.value' => ['The search.filters.0.value field must have at least 1 items.'],
            ]],
            'in with a scalar' => [['filters' => [['field' => 'views', 'operator' => 'in', 'value' => 3]]], [
                'search.filters.0.value' => ['The search.filters.0.value field must be an array.', 'The search.filters.0.value field must be a list.'],
            ]],
            'not in with an item of the wrong type' => [['filters' => [['field' => 'views', 'operator' => 'not in', 'value' => [3, 'many']]]], [
                'search.filters.0.value.1' => ['The search.filters.0.value.1 field must be an integer.'],
            ]],
            'between with one value' => [['filters' => [['field' => 'views', 'operator' => 'between', 'value' => [3]]]], [
                'search.filters.0.value' => ['The search.filters.0.value field must contain 2 items.'],
            ]],
            'not between with three values' => [['filters' => [['field' => 'views', 'operator' => 'not between', 'value' => [1, 2, 3]]]], [
                'search.filters.0.value' => ['The search.filters.0.value field must contain 2 items.'],
            ]],
            'an integer that is not an integer' => [['filters' => [['field' => 'views', 'value' => 'many']]], [
                'search.filters.0.value' => ['The search.filters.0.value field must be an integer.'],
            ]],
            'a date that is not a date' => [['filters' => [['field' => 'published_on', 'operator' => '>=', 'value' => 'soon']]], [
                'search.filters.0.value' => ['The search.filters.0.value field must be a valid date.'],
            ]],
            'an enum value that is not a case' => [['filters' => [['field' => 'status', 'value' => 'archived']]], [
                'search.filters.0.value' => ['The selected search.filters.0.value is invalid.'],
            ]],
            'a pattern that is not a string' => [['filters' => [['field' => 'title', 'operator' => 'ilike', 'value' => 3]]], [
                'search.filters.0.value' => ['The search.filters.0.value field must be a string.'],
            ]],
            'an operator the custom filter of a relation field does not declare' => [['filters' => [['field' => 'author.full_name', 'operator' => 'in', 'value' => ['Ada']]]], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'a nested filter equal to null' => [['filters' => [['nested' => [['field' => 'views', 'value' => 3], ['field' => 'title', 'value' => null]]]]], [
                'search.filters.0.nested.1.value' => ['The search.filters.0.nested.1.value field is required.'],
            ]],
            'an include filter with an operator the field does not support' => [['includes' => [['relation' => 'author', 'filters' => [['field' => 'first_name', 'operator' => '>', 'value' => 'A']]]]], [
                'search.includes.0.filters.0.operator' => ['The selected search.includes.0.filters.0.operator is invalid.'],
            ]],
            'an include filter equal to null' => [['includes' => [['relation' => 'author', 'filters' => [['field' => 'last_name', 'operator' => '!=', 'value' => null]]]]], [
                'search.includes.0.filters.0.value' => ['The search.includes.0.filters.0.value field is required.'],
            ]],
        ];
    }

    #[Test]
    public function it_validates_the_filters_of_an_aggregate_as_the_filters_of_its_relation(): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => ['aggregates' => [[
            'relation' => 'posts',
            'type' => 'count',
            'filters' => [
                ['field' => 'views', 'operator' => 'between', 'value' => [1]],
                ['field' => 'summary', 'value' => null],
                ['field' => 'views', 'operator' => 'like', 'value' => '1%'],
            ],
        ]]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.aggregates.0.filters.0.value' => ['The search.aggregates.0.filters.0.value field must contain 2 items.'],
            'search.aggregates.0.filters.1.value' => ['The search.aggregates.0.filters.1.value field is required.'],
            'search.aggregates.0.filters.2.operator' => ['The selected search.aggregates.0.filters.2.operator is invalid.'],
        ]);
    }

    #[Test]
    public function it_compares_with_the_equal_operator_when_none_is_given(): void
    {
        Post::factory()->create(['title' => 'Annual report', 'views' => 3]);
        $match = Post::factory()->create(['title' => 'Team offsite', 'views' => 5]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'views', 'value' => 5]]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [$match->id]);
    }

    #[Test]
    public function it_requires_an_operator_on_a_field_that_does_not_accept_the_equal_operator(): void
    {
        Rest::resource('pattern-authors', PatternAuthorController::class)->register();
        Author::factory()->create(['last_name' => 'Lovelace']);
        $hopper = Author::factory()->create(['last_name' => 'Hopper']);

        $missing = $this->postJson('/pattern-authors/search', ['search' => ['filters' => [['field' => 'last_name', 'value' => 'Hopper']]]]);
        $given = $this->postJson('/pattern-authors/search', ['search' => ['filters' => [['field' => 'last_name', 'operator' => 'like', 'value' => 'Hop%']]]]);

        $missing->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.operator' => ['The search.filters.0.operator field is required.'],
        ]);
        $given->assertOk()->assertJsonPath('data.*.id', [$hopper->id]);
    }

    #[Test]
    public function it_finds_the_rows_with_no_value_through_the_is_null_operator(): void
    {
        $empty = Post::factory()->create(['summary' => null]);
        Post::factory()->create(['summary' => 'A look back at the year.']);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'summary', 'operator' => 'is null']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [$empty->id]);
    }
}

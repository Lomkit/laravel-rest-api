<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AggregateFieldsTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /** @param list<float|string|null> $values */
    #[Test]
    #[DataProvider('acceptedFields')]
    public function it_aggregates_a_field_the_function_accepts(string $type, string $field, array $values): void
    {
        $ada = Author::factory()->create();
        Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing']);
        Post::factory()->for($ada)->create(['title' => 'Annual report', 'views' => 30, 'rating' => 4.5]);
        Post::factory()->for($ada)->create(['title' => 'Spring launch', 'views' => 120, 'rating' => 3.0]);

        $response = $this->postJson('/api/authors/search', [
            'search' => [
                'selects' => [['field' => 'first_name']],
                'aggregates' => [['relation' => 'posts', 'type' => $type, 'field' => $field]],
            ],
        ]);

        $key = "posts_{$type}_{$field}";
        $response->assertOk();
        $this->assertSame(['first_name', $key], array_keys($response->json('data.0')));
        $this->assertSame(['Alan', 'Ada'], array_column($response->json('data'), 'first_name'));
        $this->assertSame($values, array_map(
            fn (mixed $value): float|string|null => is_numeric($value) ? (float) $value : $value,
            array_column($response->json('data'), $key),
        ));
    }

    /** @return array<string, array{string, string, list<float|string|null>}> */
    public static function acceptedFields(): array
    {
        return [
            'min of an integer field' => ['min', 'views', [null, 30.0]],
            'max of an integer field' => ['max', 'views', [null, 120.0]],
            'min of a text field' => ['min', 'title', [null, 'Annual report']],
            'max of a text field' => ['max', 'title', [null, 'Spring launch']],
            'avg of an integer field' => ['avg', 'views', [null, 75.0]],
            'sum of an integer field' => ['sum', 'views', [null, 150.0]],
            'avg of a number field' => ['avg', 'rating', [null, 3.75]],
            'sum of a number field' => ['sum', 'rating', [null, 7.5]],
        ];
    }

    /** @param array<string, string> $aggregate */
    #[Test]
    #[DataProvider('uuidKeyAggregates')]
    public function it_aggregates_the_uuid_keys_of_a_relation(array $aggregate, string $key, string $value): void
    {
        $ada = Author::factory()->create();
        Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing']);
        Draft::factory()->for($ada)->create(['id' => '019a3601-3b9a-7d02-8c47-0e5a9f6b1d38']);
        Draft::factory()->for($ada)->create(['id' => '019a3612-04d8-7c15-a2e9-73b6d0f48c21']);
        Draft::factory()->for($ada)->create(['id' => '019a35f0-7c2e-7a41-9b3d-5e8f1c2a4b60']);

        $response = $this->postJson('/api/authors/search', [
            'search' => [
                'selects' => [['field' => 'first_name']],
                'aggregates' => [['relation' => 'drafts', 'field' => 'id', ...$aggregate]],
            ],
        ]);

        $response->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => [
                ['first_name' => 'Alan', $key => null],
                ['first_name' => 'Ada', $key => $value],
            ],
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => 2,
            'total' => 2,
            'meta' => [],
        ]);
    }

    /** @return array<string, array{array<string, string>, string, string}> */
    public static function uuidKeyAggregates(): array
    {
        return [
            'min' => [['type' => 'min'], 'drafts_min_id', '019a35f0-7c2e-7a41-9b3d-5e8f1c2a4b60'],
            'max' => [['type' => 'max'], 'drafts_max_id', '019a3612-04d8-7c15-a2e9-73b6d0f48c21'],
            'max under an alias' => [['type' => 'max', 'alias' => 'latest_draft'], 'latest_draft', '019a3612-04d8-7c15-a2e9-73b6d0f48c21'],
        ];
    }

    #[Test]
    #[DataProvider('refusedFields')]
    public function it_rejects_a_field_the_function_does_not_accept(string $resource, string $relation, string $type, string $field): void
    {
        $response = $this->postJson("/api/{$resource}/search", [
            'search' => ['aggregates' => [['relation' => $relation, 'type' => $type, 'field' => $field]]],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.aggregates.0.field is invalid.',
            'errors' => [
                'search.aggregates.0.field' => ['The selected search.aggregates.0.field is invalid.'],
            ],
        ]);
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function refusedFields(): array
    {
        return [
            'min of a virtual field with a custom sort' => ['posts', 'author', 'min', 'full_name'],
            'max of a virtual field' => ['posts', 'author', 'max', 'initials'],
            'min of a write only field' => ['posts', 'author', 'min', 'api_token'],
            'max of a field that is not sortable' => ['posts', 'author', 'max', 'id'],
            'min of a json field' => ['posts', 'author', 'min', 'settings'],
            'max of a json field' => ['authors', 'posts', 'max', 'metadata'],
            'min of a boolean field' => ['authors', 'posts', 'min', 'featured'],
            'max of a boolean field' => ['authors', 'posts', 'max', 'featured'],
            'avg of a text field' => ['authors', 'posts', 'avg', 'title'],
            'sum of a text field' => ['authors', 'posts', 'sum', 'title'],
            'sum of a date field' => ['authors', 'posts', 'sum', 'published_on'],
            'avg of an id field' => ['authors', 'posts', 'avg', 'id'],
            'sum of a json field' => ['authors', 'posts', 'sum', 'metadata'],
        ];
    }

    #[Test]
    #[DataProvider('fieldlessFunctions')]
    public function it_rejects_a_field_on_a_function_that_takes_none(string $type): void
    {
        $response = $this->postJson('/api/authors/search', [
            'search' => ['aggregates' => [['relation' => 'posts', 'type' => $type, 'field' => 'views']]],
        ]);

        $message = "The search.aggregates.0.field field is prohibited when search.aggregates.0.type is {$type}.";
        $response->assertUnprocessable()->assertExactJson([
            'message' => $message,
            'errors' => ['search.aggregates.0.field' => [$message]],
        ]);
    }

    /** @return array<string, array{string}> */
    public static function fieldlessFunctions(): array
    {
        return [
            'count' => ['count'],
            'exists' => ['exists'],
        ];
    }

    #[Test]
    public function it_rejects_an_unknown_function(): void
    {
        $response = $this->postJson('/api/authors/search', [
            'search' => ['aggregates' => [['relation' => 'posts', 'type' => 'median', 'field' => 'views']]],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.aggregates.0.type is invalid.',
            'errors' => [
                'search.aggregates.0.type' => ['The selected search.aggregates.0.type is invalid.'],
            ],
        ]);
    }
}

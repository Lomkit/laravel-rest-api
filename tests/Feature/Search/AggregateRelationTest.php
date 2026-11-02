<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Feature\Serialization\SeedsPublishingRows;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AggregateRelationTest extends PackageTestCase
{
    use SeedsPublishingRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('nested')]
    public function it_refuses_an_aggregate_on_a_nested_relation(string $uri, array $search, string $key): void
    {
        $message = "The {$key} field cannot be a nested relation. Request the aggregate inside an include instead.";

        $response = $this->postJson($uri, ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function nested(): array
    {
        return [
            'a nested relation' => [
                '/api/posts/search',
                ['aggregates' => [['relation' => 'author.posts', 'type' => 'count']]],
                'search.aggregates.0.relation',
            ],
            'a nested relation with an alias' => [
                '/api/posts/search',
                ['aggregates' => [['relation' => 'author.posts', 'type' => 'count', 'alias' => 'siblings']]],
                'search.aggregates.0.relation',
            ],
            'a nested relation with a field' => [
                '/api/authors/search',
                ['aggregates' => [['relation' => 'posts.author', 'type' => 'max', 'field' => 'first_name']]],
                'search.aggregates.0.relation',
            ],
            'a nested relation in an include' => [
                '/api/authors/search',
                ['includes' => [['relation' => 'posts', 'aggregates' => [['relation' => 'author.tags', 'type' => 'count']]]]],
                'search.includes.0.aggregates.0.relation',
            ],
        ];
    }

    #[Test]
    public function it_counts_a_deeper_relation_inside_an_include(): void
    {
        $this->seedPublishingRows();

        $response = $this->postJson('/api/posts/search', ['search' => [
            'selects' => [['field' => 'id']],
            'includes' => [[
                'relation' => 'author',
                'selects' => [['field' => 'id']],
                'aggregates' => [['relation' => 'posts', 'type' => 'count']],
            ]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'author' => ['id' => 1, 'posts_count' => 2]],
            ['id' => 2, 'author' => ['id' => 1, 'posts_count' => 2]],
            ['id' => 3, 'author' => null],
        ]);
    }
}

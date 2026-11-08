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

    /**
     * @param  array<string, mixed>  $search
     * @param  list<array<string, mixed>>  $data
     */
    #[Test]
    #[DataProvider('paths')]
    public function it_aggregates_a_relation_path(string $uri, array $search, array $data): void
    {
        $this->seedPublishingRows();

        $response = $this->postJson($uri, ['search' => [...$search, 'selects' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data', $data);
    }

    /** @return array<string, array{string, array<string, mixed>, list<array<string, mixed>>}> */
    public static function paths(): array
    {
        return [
            'a relation path' => [
                '/api/posts/search',
                ['aggregates' => [['relation' => 'author.posts', 'type' => 'count']], 'sorts' => [['field' => 'id']]],
                [['id' => 1, 'author_posts_count' => 2], ['id' => 2, 'author_posts_count' => 2], ['id' => 3, 'author_posts_count' => 0]],
            ],
            'a relation path with an alias' => [
                '/api/posts/search',
                ['aggregates' => [['relation' => 'author.posts', 'type' => 'count', 'alias' => 'siblings']], 'sorts' => [['field' => 'id']]],
                [['id' => 1, 'siblings' => 2], ['id' => 2, 'siblings' => 2], ['id' => 3, 'siblings' => 0]],
            ],
            'a relation path with a field' => [
                '/api/authors/search',
                ['aggregates' => [['relation' => 'posts.author', 'type' => 'max', 'field' => 'first_name']], 'sorts' => [['field' => 'first_name']]],
                [['id' => 1, 'posts_author_max_first_name' => 'Ada'], ['id' => 2, 'posts_author_max_first_name' => null]],
            ],
            'a relation path in an include' => [
                '/api/authors/search',
                ['includes' => [['relation' => 'posts', 'selects' => [['field' => 'id']], 'aggregates' => [['relation' => 'author.tags', 'type' => 'count']]]], 'sorts' => [['field' => 'first_name']]],
                [['id' => 1, 'posts' => [['id' => 2, 'author_tags_count' => 1], ['id' => 1, 'author_tags_count' => 1]], '@more' => []], ['id' => 2, 'posts' => [], '@more' => []]],
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

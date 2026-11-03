<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Lomkit\Rest\Tests\Feature\Serialization\Stubs\Charts\Album;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\Charts\Charts;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\Charts\Rating;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AggregateKeysTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Charts];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Album::create(['id' => 1, 'title' => 'Blue']);
        Album::create(['id' => 2, 'title' => 'Kind']);
        Rating::create(['id' => 1, 'album_id' => 1, 'starCount' => 4]);
        Rating::create(['id' => 2, 'album_id' => 1, 'starCount' => 5]);
    }

    #[Test]
    public function it_returns_the_value_of_an_aggregate_whose_field_has_capital_letters(): void
    {
        $response = $this->postJson('/api/albums/search', ['search' => [
            'aggregates' => [['relation' => 'ratings', 'type' => 'max', 'field' => 'starCount']],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'title' => 'Blue', 'ratings_max_starcount' => 5],
            ['id' => 2, 'title' => 'Kind', 'ratings_max_starcount' => null],
        ]);
    }

    #[Test]
    public function it_returns_the_value_of_an_aliased_aggregate_whose_field_has_capital_letters(): void
    {
        $response = $this->postJson('/api/albums/search', ['search' => [
            'aggregates' => [['relation' => 'ratings', 'type' => 'max', 'field' => 'starCount', 'alias' => 'best']],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'title' => 'Blue', 'best' => 5],
            ['id' => 2, 'title' => 'Kind', 'best' => null],
        ]);
    }

    /** @param list<int|bool> $values */
    #[Test]
    #[DataProvider('emptyFields')]
    public function it_returns_a_count_or_exists_sent_with_an_empty_field(string $type, array $values): void
    {
        $response = $this->postJson('/api/albums/search', ['search' => [
            'aggregates' => [['relation' => 'ratings', 'type' => $type, 'field' => []]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.ratings_'.$type, $values);
    }

    /** @return array<string, array{string, list<int|bool>}> */
    public static function emptyFields(): array
    {
        return [
            'count' => ['count', [2, 0]],
            'exists' => ['exists', [true, false]],
        ];
    }
}

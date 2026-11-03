<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RowKeyReservationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['rest.morph.type_key' => 'kind']);
        Book::create(['id' => 1, 'title' => 'Dune']);
        Label::create(['id' => 1, 'text' => 'classic', 'labelable_type' => Book::class, 'labelable_id' => 1]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function targetKeys(): array
    {
        return [
            'an aggregate alias' => [['relation' => 'labelable', 'aggregates' => [['relation' => 'labels', 'type' => 'count', 'alias' => 'kind']]], 'search.includes.0.aggregates.0.alias'],
            'an include alias' => [['relation' => 'labelable', 'includes' => [['relation' => 'loan', 'alias' => 'kind']]], 'search.includes.0.includes.0.alias'],
        ];
    }

    /** @param array<string, mixed> $include */
    #[Test]
    #[DataProvider('targetKeys')]
    public function it_reserves_the_type_key_in_a_morph_to_target(array $include, string $key): void
    {
        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [$include]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [$key => ["The {$key} is reserved."]]);
    }

    #[Test]
    public function it_leaves_the_type_key_free_on_other_rows(): void
    {
        $response = $this->postJson('/api/books/search', ['search' => ['aggregates' => [['relation' => 'labels', 'type' => 'count', 'alias' => 'kind']]]]);

        $response->assertOk()->assertJsonPath('data.*.kind', [1]);
    }
}

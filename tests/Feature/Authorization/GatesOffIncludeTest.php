<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\UngatedShelfBookController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\UngatedShelfLabelController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GatesOffIncludeTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['book' => Book::class, 'shelf' => Shelf::class], false);
        Rest::resource('ungated-shelf-books', UngatedShelfBookController::class)->register();
        Rest::resource('ungated-shelf-labels', UngatedShelfLabelController::class)->register();
        Book::query()->create(['id' => 1, 'title' => 'Dune']);
        Shelf::query()->create(['id' => 1, 'name' => 'Classics']);
        Label::query()->create(['id' => 1, 'text' => 'classic', 'labelable_type' => 'book', 'labelable_id' => 1]);
        Label::query()->create(['id' => 2, 'text' => 'shelved', 'labelable_type' => 'shelf', 'labelable_id' => 1]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('gatesOffIncludes')]
    public function it_refuses_gates_in_an_include_whose_resource_turns_them_off(string $uri, array $body, string $key): void
    {
        $response = $this->postJson($uri, $body);

        $this->assertExactErrors($response, [$key => ["The {$key} field is prohibited: the included resource turns its gates off."]]);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function gatesOffIncludes(): array
    {
        $books = '/ungated-shelf-books/search';
        $labels = '/ungated-shelf-labels/search';

        return [
            'an include' => [$books, ['search' => ['includes' => [['relation' => 'shelves', 'gates' => ['view']]]]], 'search.includes.0.gates'],
            'an empty list' => [$books, ['search' => ['includes' => [['relation' => 'shelves', 'gates' => []]]]], 'search.includes.0.gates'],
            'a value that is not a list' => [$books, ['search' => ['includes' => [['relation' => 'shelves', 'gates' => 'view']]]], 'search.includes.0.gates'],
            'a MorphTo include with one type' => [
                $books,
                ['search' => ['includes' => [['relation' => 'label', 'includes' => [['relation' => 'labelable', 'gates' => ['view']]]]]]],
                'search.includes.0.includes.0.gates',
            ],
            'a MorphTo type block' => [$labels, ['search' => ['includes' => [['relation' => 'labelable', 'types' => ['shelf' => ['gates' => ['view']]]]]]], 'search.includes.0.types.shelf.gates'],
            'an include in a MorphTo type block' => [
                $labels,
                ['search' => ['includes' => [['relation' => 'labelable', 'types' => ['book' => ['includes' => [['relation' => 'shelves', 'gates' => ['view']]]]]]]]],
                'search.includes.0.types.book.includes.0.gates',
            ],
            'an include of the records of a mutation' => [
                '/ungated-shelf-books/mutate',
                [
                    'mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Dune']]],
                    'response' => ['includes' => [['relation' => 'shelves', 'gates' => ['view']]]],
                ],
                'response.includes.0.gates',
            ],
        ];
    }

    #[Test]
    public function it_answers_the_gates_of_a_type_whose_resource_keeps_them_on(): void
    {
        $response = $this->postJson('/ungated-shelf-labels/search', ['search' => [
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'id']],
            'includes' => [['relation' => 'labelable', 'types' => ['book' => ['selects' => [['field' => 'id']], 'gates' => ['view']], 'shelf' => ['selects' => [['field' => 'name']]]]]],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'labelable' => ['@type' => 'book', 'id' => 1, 'gates' => ['authorized_to_view' => ['allowed' => true, 'message' => null]]]],
            ['id' => 2, 'labelable' => ['@type' => 'shelf', 'name' => 'Classics']],
        ]);
    }
}

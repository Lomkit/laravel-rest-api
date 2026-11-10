<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RepeatedIncludeTest extends PackageTestCase
{
    private const array ID = [['field' => 'id']];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Book::query()->create(['id' => 1, 'title' => 'Dune']);
        Loan::query()->create(['id' => 1, 'book_id' => 1, 'borrower' => 'Ada']);
        Label::query()->create(['id' => 1, 'labelable_id' => 1, 'labelable_type' => Book::class, 'text' => 'classic']);
        Label::query()->create(['id' => 2, 'labelable_id' => 1, 'labelable_type' => Book::class, 'text' => 'novel']);
    }

    /**
     * @param  list<array<string, mixed>>  $includes
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('repeatedIncludes')]
    public function it_asks_for_an_alias_when_two_includes_load_the_same_relation(string $uri, array $includes, array $errors): void
    {
        $response = $this->postJson($uri, ['search' => ['selects' => self::ID, 'includes' => $includes]]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{string, list<array<string, mixed>>, array<string, list<string>>}> */
    public static function repeatedIncludes(): array
    {
        $nested = 'loads a relation that another include already loads: nest it in an include with an alias instead.';

        return [
            'one relation twice' => ['/api/books/search', [
                ['relation' => 'labels', 'filters' => [['field' => 'id', 'value' => 1]]],
                ['relation' => 'labels', 'filters' => [['field' => 'id', 'value' => 2]]],
            ], [
                'search.includes.0.alias' => ['The search.includes.0.alias field is required, because the key labels is already used.'],
                'search.includes.1.alias' => ['The search.includes.1.alias field is required, because the key labels is already used.'],
            ]],
            'one relation twice in an include' => ['/api/loans/search', [
                ['relation' => 'book', 'includes' => [['relation' => 'labels', 'filters' => [['field' => 'id', 'value' => 1]]], ['relation' => 'labels']]],
            ], [
                'search.includes.0.includes.0.alias' => ['The search.includes.0.includes.0.alias field is required, because the key labels is already used.'],
                'search.includes.0.includes.1.alias' => ['The search.includes.0.includes.1.alias field is required, because the key labels is already used.'],
            ]],
            'one dotted relation twice' => ['/api/loans/search', [
                ['relation' => 'book.labels', 'filters' => [['field' => 'id', 'value' => 1]]],
                ['relation' => 'book.labels', 'filters' => [['field' => 'id', 'value' => 2]]],
            ], [
                'search.includes.0.relation' => ['The search.includes.0.relation field '.$nested],
                'search.includes.1.relation' => ['The search.includes.1.relation field '.$nested],
            ]],
            'a dotted relation that a nested include loads' => ['/api/loans/search', [
                ['relation' => 'book', 'includes' => [['relation' => 'labels', 'filters' => [['field' => 'id', 'value' => 1]]]]],
                ['relation' => 'book.labels', 'filters' => [['field' => 'id', 'value' => 2]]],
            ], [
                'search.includes.0.alias' => ['The search.includes.0.alias field is required, because the key book is already used.'],
                'search.includes.1.relation' => ['The search.includes.1.relation field '.$nested],
            ]],
            'a morph to relation twice with other types' => ['/api/books/search', [
                ['relation' => 'labels.labelable', 'types' => [Book::class => ['selects' => self::ID]]],
                ['relation' => 'labels.labelable', 'types' => [Shelf::class => ['selects' => self::ID]]],
            ], [
                'search.includes.0.relation' => ['The search.includes.0.relation field '.$nested],
                'search.includes.1.relation' => ['The search.includes.1.relation field '.$nested],
            ]],
        ];
    }

    #[Test]
    public function it_loads_a_dotted_include_below_an_include_of_its_first_relation(): void
    {
        $response = $this->postJson('/api/loans/search', ['search' => ['selects' => self::ID, 'includes' => [
            ['relation' => 'book', 'selects' => [['field' => 'title']]],
            ['relation' => 'book.labels', 'selects' => self::ID, 'filters' => [['field' => 'id', 'value' => 2]]],
        ]]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, 'book' => ['title' => 'Dune', 'labels' => [['id' => 2]], '@more' => []]]]);
    }

    #[Test]
    public function it_loads_one_relation_twice_under_two_aliases(): void
    {
        $response = $this->postJson('/api/books/search', ['search' => ['selects' => self::ID, 'includes' => [
            ['relation' => 'labels', 'selects' => self::ID, 'filters' => [['field' => 'id', 'value' => 1]]],
            ['relation' => 'labels', 'alias' => 'novels', 'selects' => self::ID, 'filters' => [['field' => 'id', 'value' => 2]]],
        ]]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, 'labels' => [['id' => 1]], 'novels' => [['id' => 2]], '@more' => []]]);
    }
}

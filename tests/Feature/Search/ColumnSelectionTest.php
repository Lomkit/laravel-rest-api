<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ColumnSelectionTest extends PackageTestCase
{
    private const array ID = [['field' => 'id']];

    private const string BOOKS = 'select books.id from books order by books.id desc limit 50 offset 0';

    private const string LOANS = 'select loans.id, loans.book_id from loans order by loans.id desc limit 50 offset 0';

    private const string LABELS = 'select * from (select labels.id, labels.labelable_id, labels.labelable_type, row_number() over (partition by labels.labelable_id order by labels.id desc) as laravel_row from labels where labels.labelable_id in (1, 2) and labels.labelable_type = ?) as laravel_table where laravel_row <= 10 order by laravel_row';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1, 2] as $id) {
            Book::query()->create(['id' => $id, 'title' => 'Book '.$id]);
            Shelf::query()->create(['id' => $id, 'name' => 'Shelf '.$id]);
            DB::table('book_shelf')->insert(['book_id' => $id, 'shelf_id' => $id]);
        }

        foreach ([1, 2, 3] as $id) {
            Loan::query()->create(['id' => $id, 'book_id' => $id === 2 ? 2 : 1, 'borrower' => 'Reader '.$id]);
            Label::query()->create(['id' => $id, 'labelable_id' => $id === 2 ? 2 : 1, 'labelable_type' => Book::class, 'text' => 'Label '.$id]);
        }
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<string>  $queries
     */
    #[Test]
    #[DataProvider('levels')]
    public function it_loads_the_columns_that_each_level_renders(string $uri, array $search, array $queries): void
    {
        DB::enableQueryLog();

        $this->postJson($uri, ['search' => $search])->assertOk();

        $this->assertSame($queries, self::loggedQueries());
    }

    /** @return array<string, array{string, array<string, mixed>, list<string>}> */
    public static function levels(): array
    {
        return [
            'the root' => ['/api/books/search', ['selects' => [['field' => 'title']]], ['select books.title, books.id from books order by books.id desc limit 50 offset 0']],
            'a belongs to' => ['/api/loans/search', ['selects' => self::ID, 'includes' => [['relation' => 'book', 'selects' => self::ID]]], [self::LOANS, 'select books.id from books where books.id in (1, 2)']],
            'a has one' => ['/api/books/search', ['selects' => self::ID, 'includes' => [['relation' => 'loan', 'selects' => self::ID]]], [self::BOOKS, 'select * from (select loans.id, loans.book_id, row_number() over (partition by loans.book_id order by loans.id desc) as laravel_row from loans where loans.book_id in (1, 2)) as laravel_table where laravel_row <= 10 order by laravel_row']],
            'a has one of many' => ['/api/books/search', ['selects' => self::ID, 'includes' => [['relation' => 'latestLoan', 'selects' => self::ID]]], [self::BOOKS, 'select * from (select loans.id, loans.book_id, row_number() over (partition by loans.book_id order by loans.id desc) as laravel_row from loans inner join (select MAX(loans.id) as id_aggregate, loans.book_id from loans where loans.book_id in (1, 2) group by loans.book_id) as latestOfMany on latestOfMany.id_aggregate = loans.id and latestOfMany.book_id = loans.book_id) as laravel_table where laravel_row <= 10 order by laravel_row']],
            'a morph many' => ['/api/books/search', ['selects' => self::ID, 'includes' => [['relation' => 'labels', 'selects' => self::ID]]], [self::BOOKS, self::LABELS]],
            'an aliased include' => ['/api/books/search', ['selects' => self::ID, 'includes' => [['relation' => 'labels', 'alias' => 'notes', 'selects' => self::ID]]], [self::BOOKS, self::LABELS]],
            'a belongs to many' => ['/api/books/search', ['selects' => self::ID, 'includes' => [['relation' => 'shelves', 'selects' => self::ID]]], [self::BOOKS, 'select * from (select shelves.id, book_shelf.book_id as pivot_book_id, book_shelf.shelf_id as pivot_shelf_id, row_number() over (partition by book_shelf.book_id order by shelves.id desc) as laravel_row from shelves inner join book_shelf on shelves.id = book_shelf.shelf_id where book_shelf.book_id in (1, 2)) as laravel_table where laravel_row <= 10 order by laravel_row']],
            'a morph to' => ['/api/labels/search', ['selects' => self::ID, 'includes' => [['relation' => 'labelable', 'selects' => self::ID]]], ['select labels.id, labels.labelable_type, labels.labelable_id from labels order by labels.id desc limit 50 offset 0', 'select books.id from books where books.id in (1, 2)']],
            'a dotted include' => ['/api/loans/search', ['selects' => self::ID, 'includes' => [['relation' => 'book.labels', 'selects' => self::ID]]], [self::LOANS, 'select books.id, books.title from books where books.id in (1, 2)', self::LABELS]],
        ];
    }

    #[Test]
    public function it_quotes_the_selected_columns_for_the_driver(): void
    {
        DB::enableQueryLog();

        $this->postJson('/api/books/search', ['search' => ['selects' => [['field' => 'title']]]])->assertOk();

        $expected = DB::connection()->getDriverName() === 'mysql'
            ? 'select `books`.`title`, `books`.`id` from `books` order by `books`.`id` desc limit 50 offset 0'
            : 'select "books"."title", "books"."id" from "books" order by "books"."id" desc limit 50 offset 0';
        $this->assertSame($expected, DB::getQueryLog()[0]['query']);
    }

    #[Test]
    public function it_loads_the_columns_that_the_records_of_a_mutation_render(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/books/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Persuasion']]],
            'response' => ['selects' => [['field' => 'title']]],
        ]);

        $response->assertOk()->assertJsonPath('records', [['title' => 'Persuasion']]);
        $this->assertSame('select books.title, books.id from books where books.id in (1)', Arr::last(self::loggedQueries()));
    }

    /** @return list<string> */
    private static function loggedQueries(): array
    {
        return array_map(static fn (array $query): string => str_replace(['"', '`'], '', $query['query']), DB::getQueryLog());
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\BookLoanController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\CountedBookController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ScopedLoanController;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\EagerLoanController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class EagerLoadColumnsTest extends PackageTestCase
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

        Rest::resource('eager-loans', EagerLoanController::class)->register();
        Rest::resource('book-loans', BookLoanController::class)->register();
        Rest::resource('scoped-loans', ScopedLoanController::class)->register();
        Rest::resource('counted-books', CountedBookController::class)->register();
        Book::query()->create(['id' => 1, 'title' => 'Dune']);
        Loan::query()->create(['id' => 1, 'book_id' => 1, 'borrower' => 'Ada']);
        Loan::query()->create(['id' => 2, 'book_id' => null, 'borrower' => 'Bob']);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('eagerLoads')]
    public function it_loads_the_keys_of_a_relation_that_the_query_loads_outside_the_search(string $uri, array $search): void
    {
        DB::enableQueryLog();

        $response = $this->postJson($uri, ['search' => ['selects' => [['field' => 'borrower']], ...$search]]);

        $response->assertOk()->assertJsonPath('data', [['borrower' => 'Bob'], ['borrower' => 'Ada']]);
        $this->assertSame([
            'select count(*) as aggregate from loans',
            'select loans.borrower, loans.id, loans.book_id from loans order by loans.id desc limit 50 offset 0',
            'select * from books where books.id in (1)',
        ], self::loggedQueries());
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function eagerLoads(): array
    {
        return [
            'a relation that the model always loads' => ['/eager-loans/search', []],
            'a relation that an instruction loads' => ['/book-loans/search', ['instructions' => [['name' => 'with-book']]]],
            'a relation that a global scope loads' => ['/scoped-loans/search', []],
        ];
    }

    /** @param array<string, mixed> $include */
    #[Test]
    #[DataProvider('subqueries')]
    public function it_selects_the_columns_in_place_of_the_columns_that_laravel_selects_next_to_a_subquery(array $include, string $key, string $query): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/counted-books/search', ['search' => ['selects' => [['field' => 'id']], 'includes' => [$include]]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, $key => [['borrower' => 'Ada']]]]);
        $this->assertSame($query, self::loggedQueries()[2]);
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function subqueries(): array
    {
        return [
            'a count that the model always loads' => [
                ['relation' => 'countedLoans', 'selects' => [['field' => 'borrower']]],
                'counted_loans',
                'select * from (select loans.borrower, loans.id, loans.book_id, (select count(*) from books where loans.book_id = books.id) as book_count, row_number() over (partition by loans.book_id order by loans.id desc) as laravel_row from loans where loans.book_id in (1)) as laravel_table where laravel_row <= 10 order by laravel_row',
            ],
            'a subquery that an instruction selects' => [
                ['relation' => 'loans', 'selects' => [['field' => 'borrower']], 'instructions' => [['name' => 'book-title']]],
                'loans',
                'select * from (select loans.borrower, loans.id, loans.book_id, (select title from books where books.id = loans.book_id limit 1) as book_title, row_number() over (partition by loans.book_id order by loans.id desc) as laravel_row from loans where loans.book_id in (1)) as laravel_table where laravel_row <= 10 order by laravel_row',
            ],
        ];
    }

    /** @return list<string> */
    private static function loggedQueries(): array
    {
        return array_map(static fn (array $query): string => str_replace(['"', '`'], '', $query['query']), DB::getQueryLog());
    }
}

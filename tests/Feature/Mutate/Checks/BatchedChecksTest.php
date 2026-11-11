<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks;

use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksPolicy;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class BatchedChecksTest extends WriteChecksTestCase
{
    private const array ROWS = ['Book(Dune) [Shelf(Classics)]', 'Book(Emma) [Shelf(Drama), Shelf(Hidden), Shelf(Poetry)]'];

    /**
     * @param  list<array<string, mixed>>  $mutate
     * @param  array<string, mixed>  $denial
     */
    #[Test]
    #[DataProvider('refusedChecks')]
    public function it_refuses_a_row_check_before_the_first_write(string $endpoint, array $mutate, string $refused, array $denial): void
    {
        ChecksPolicy::$refused = [$refused];

        $response = $this->mutate($endpoint, $mutate);

        $response->assertForbidden();
        $response->assertExactJson(['message' => 'Refused: '.$refused.'.', 'denials' => [[...$denial, 'message' => 'Refused: '.$refused.'.']]]);
        $this->assertSame([], self::writes());
        $this->assertSame('rollback', TracingPolicy::$trace[array_key_last(TracingPolicy::$trace)]);
        $this->assertSame(self::ROWS, self::rows('books'));
        $this->assertSame(['Loan(Ada) Book(Dune)', 'Loan(Grace) -'], self::rows('loans'));
        $this->assertSame(['Label(classic) Book Book(Dune)', 'Label(loose) - -'], self::rows('labels'));
    }

    /** @return array<string, array{string, list<array<string, mixed>>, string, array<string, mixed>}> */
    public static function refusedChecks(): array
    {
        return [
            'an update' => ['/api/books/mutate', [['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma II']]], 'update Book(Emma) {"title":"Emma II"}', ['path' => 'mutate.0', 'ability' => 'update', 'key' => 102]],
            'a creation' => ['/api/books/mutate', [['operation' => 'create', 'attributes' => ['title' => 'Walden']]], 'create Book* {"title":"Walden"}', ['path' => 'mutate.0', 'ability' => 'create']],
            'an attached row' => ['/api/books/mutate', [self::shelves(101, [['operation' => 'attach', 'key' => [102]]])], 'view Shelf(Poetry)', ['path' => 'mutate.0.relations.shelves.0', 'ability' => 'view', 'key' => 102]],
            'a moved child' => ['/api/books/mutate', [self::loan(102)], 'update Loan(Grace) {"book_id":102}', ['path' => 'mutate.0.relations.loan', 'ability' => 'update', 'key' => 102]],
            'a declared link' => ['/api/books/mutate', [self::loan(102)], 'attachLoan Book(Emma) Loan(Grace)', ['path' => 'mutate.0.relations.loan', 'ability' => 'attachLoan', 'key' => 102]],
            'a link without a declared ability' => ['/api/loans/mutate', [[
                'operation' => 'update',
                'key' => 101,
                'relations' => ['book' => ['operation' => 'attach', 'key' => 101, 'relations' => ['labels' => [['operation' => 'attach', 'key' => [102]]]]]],
            ]], 'update Book(Dune) []', ['path' => 'mutate.0.relations.book.relations.labels.0', 'ability' => 'update', 'key' => 102]],
            'a row that a sync detaches' => ['/api/books/mutate', [self::shelves(102, [['operation' => 'sync', 'key' => [102]]])], 'detachShelf Book(Emma) Shelf(Drama)', ['path' => 'mutate.0.relations.shelves.0', 'ability' => 'detachShelf', 'key' => 103]],
        ];
    }

    #[Test]
    public function it_checks_every_row_of_the_request_then_writes_in_request_order(): void
    {
        $response = $this->mutate('/api/books/mutate', self::nestedWrites([101, 102, 103]));
        $trace = TracingPolicy::$trace;

        $walden = Book::query()
            ->where('title', 'Walden')
            ->sole()
            ->getKey();
        $response->assertOk();
        $this->assertSame([
            'policy create',
            'policy create',
            'sql select * from shelves where (shelves.name != ?) and shelves.id in (101, 102, 103)',
            'sql select * from books where books.id in (102)',
            'begin',
            'sql select shelves.*, book_shelf.book_id as pivot_book_id, book_shelf.shelf_id as pivot_shelf_id from shelves inner join book_shelf on shelves.id = book_shelf.shelf_id where book_shelf.book_id = ? and (shelves.name != ?) limit 1004',
            'policy create Book* {"title":"Walden"}',
            'policy create Loan* {"borrower":"Linus","book_id":null}',
            'policy view Shelf(Classics)',
            'policy update Book(Emma) {"title":"Emma II"}',
            'policy view Shelf(Poetry)',
            'policy view Shelf(Drama)',
            'policy attachShelf Book(Emma) Shelf(Classics)',
            'hook mutating Book* []',
            'sql insert into books (title, updated_at, created_at) values (?, ?, ?)',
            'saved Book(Walden)',
            'hook mutating Loan* []',
            'policy attachLoan Book(Walden) Loan* {"borrower":"Linus","book_id":'.$walden.'}',
            'sql insert into loans (borrower, book_id, updated_at, created_at) values (?, ?, ?, ?)',
            'saved Loan(Linus)',
            'hook mutated Loan(Linus)',
            'policy attachShelf Book(Walden) Shelf(Classics)',
            'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
            'hook mutated Book(Walden)',
            'hook mutating Book(Emma)',
            'sql update books set title = ?, updated_at = ? where id = ?',
            'saved Book(Emma II)',
            'sql select shelf_id from book_shelf where book_shelf.book_id = ? and book_shelf.shelf_id in (?)',
            'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
            'hook mutated Book(Emma II)',
            'commit',
        ], $trace);
        $this->assertSame([
            'Book(Dune) [Shelf(Classics)]',
            'Book(Emma II) [Shelf(Classics), Shelf(Drama), Shelf(Hidden), Shelf(Poetry)]',
            'Book(Walden) [Shelf(Classics)]',
        ], self::rows('books'));
        $this->assertSame(['Loan(Ada) Book(Dune)', 'Loan(Grace) -', 'Loan(Linus) Book(Walden)'], self::rows('loans'));
    }

    #[Test]
    public function it_reports_every_denial_of_the_request_in_request_order(): void
    {
        ChecksPolicy::$refused = ['view Shelf(Classics)', 'detachShelf Book(Emma) Shelf(Drama)'];

        $response = $this->mutate('/api/books/mutate', self::nestedWrites([101, 102]));

        $response->assertForbidden();
        $response->assertExactJson(['message' => 'Refused: view Shelf(Classics).', 'denials' => [
            ['path' => 'mutate.0.relations.shelves.0', 'ability' => 'view', 'key' => 101, 'message' => 'Refused: view Shelf(Classics).'],
            ['path' => 'mutate.1.relations.shelves.0', 'ability' => 'view', 'key' => 101, 'message' => 'Refused: view Shelf(Classics).'],
            ['path' => 'mutate.1.relations.shelves.0', 'ability' => 'detachShelf', 'key' => 103, 'message' => 'Refused: detachShelf Book(Emma) Shelf(Drama).'],
        ]]);
        $this->assertSame([], self::writes());
        $this->assertSame(self::ROWS, self::rows('books'));
    }

    /** @param list<string> $refused */
    #[Test]
    #[DataProvider('createdLinks')]
    public function it_checks_the_link_of_a_created_row_once_the_row_is_saved(array $refused, int $status, string $end): void
    {
        ChecksPolicy::$refused = $refused;

        $response = $this->mutate('/api/books/mutate', [[
            'operation' => 'create',
            'attributes' => ['title' => 'Walden'],
            'relations' => ['shelves' => [['operation' => 'attach', 'key' => [101]]]],
        ]]);

        $response->assertStatus($status);
        $this->assertSame([
            'begin',
            'policy create Book* {"title":"Walden"}',
            'policy view Shelf(Classics)',
            'hook mutating Book* []',
            'sql insert into books (title, updated_at, created_at) values (?, ?, ?)',
            'saved Book(Walden)',
            'policy attachShelf Book(Walden) Shelf(Classics)',
            ...($status === 200 ? ['sql insert into book_shelf (book_id, shelf_id) values (?, ?)', 'hook mutated Book(Walden)'] : []),
            $end,
        ], array_slice(TracingPolicy::$trace, 2));
    }

    /** @return array<string, array{list<string>, int, string}> */
    public static function createdLinks(): array
    {
        return [
            'allowed' => [[], 200, 'commit'],
            'refused' => [['attachShelf Book(Walden) Shelf(Classics)'], 403, 'rollback'],
        ];
    }

    /**
     * @param  list<int>  $synced
     * @return list<array<string, mixed>>
     */
    private static function nestedWrites(array $synced): array
    {
        return [
            [
                'operation' => 'create',
                'attributes' => ['title' => 'Walden'],
                'relations' => [
                    'loan' => ['operation' => 'create', 'attributes' => ['borrower' => 'Linus']],
                    'shelves' => [['operation' => 'attach', 'key' => [101]]],
                ],
            ],
            [...self::shelves(102, [['operation' => 'sync', 'key' => $synced]]), 'attributes' => ['title' => 'Emma II']],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private static function shelves(int $book, array $entries): array
    {
        return ['operation' => 'update', 'key' => $book, 'relations' => ['shelves' => $entries]];
    }

    /** @return array<string, mixed> */
    private static function loan(int $loan): array
    {
        return ['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'attach', 'key' => $loan]]];
    }
}

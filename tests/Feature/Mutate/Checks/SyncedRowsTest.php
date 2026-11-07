<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks;

use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use PHPUnit\Framework\Attributes\Test;

class SyncedRowsTest extends WriteChecksTestCase
{
    #[Test]
    public function it_leaves_attached_the_rows_that_the_client_cannot_write(): void
    {
        $response = $this->mutate('/api/books/mutate', [['operation' => 'update', 'key' => 102, 'relations' => ['shelves' => [
            ['operation' => 'sync', 'key' => [101, 102]],
        ]]]]);

        $response->assertOk();
        $this->assertSame([
            'sql select * from books where books.id in (102)',
            'sql select * from shelves where (shelves.name != ?) and shelves.id in (101, 102)',
            'begin',
            'sql select shelves.*, book_shelf.book_id as pivot_book_id, book_shelf.shelf_id as pivot_shelf_id from shelves inner join book_shelf on shelves.id = book_shelf.shelf_id where book_shelf.book_id = ? and (shelves.name != ?)',
            'policy update Book(Emma) []',
            'policy view Shelf(Classics)',
            'policy view Shelf(Poetry)',
            'policy attachShelf Book(Emma) Shelf(Classics)',
            'policy detachShelf Book(Emma) Shelf(Drama)',
            'hook mutating Book(Emma)',
            'saved Book(Emma)',
            'sql delete from book_shelf where book_shelf.book_id = ? and book_shelf.shelf_id in (?)',
            'sql select * from book_shelf where book_shelf.book_id = ?',
            'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
            'hook mutated Book(Emma)',
            'commit',
        ], TracingPolicy::$trace);
        $this->assertSame(['Book(Dune) [Shelf(Classics)]', 'Book(Emma) [Shelf(Classics), Shelf(Hidden), Shelf(Poetry)]'], self::rows('books'));
    }

    #[Test]
    public function it_toggles_against_the_rows_that_an_earlier_entry_attached(): void
    {
        $response = $this->mutate('/api/books/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [
            ['operation' => 'attach', 'key' => [102]],
            ['operation' => 'toggle', 'key' => [101, 102]],
        ]]]]);

        $response->assertOk();
        $this->assertSame([
            'policy update Book(Dune) []',
            'policy view Shelf(Poetry)',
            'policy attachShelf Book(Dune) Shelf(Poetry)',
            'policy view Shelf(Classics)',
            'policy detachShelf Book(Dune) Shelf(Classics)',
            'policy detachShelf Book(Dune) Shelf(Poetry)',
        ], array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'policy '))));
        $this->assertSame(['Book(Dune) []', 'Book(Emma) [Shelf(Drama), Shelf(Hidden), Shelf(Poetry)]'], self::rows('books'));
    }

    #[Test]
    public function it_detaches_a_row_that_an_earlier_entry_created_when_a_sync_leaves_it_out(): void
    {
        $response = $this->mutate('/api/books/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Fresh']],
            ['operation' => 'sync', 'key' => [102]],
        ]]]]);

        $response->assertOk();
        $this->assertSame([
            'policy create',
            'policy update Book(Dune) []',
            'policy create Shelf* {"name":"Fresh"}',
            'policy view Shelf(Poetry)',
            'policy attachShelf Book(Dune) Shelf(Poetry)',
            'policy detachShelf Book(Dune) Shelf(Classics)',
            'policy attachShelf Book(Dune) Shelf(Fresh)',
            'policy detachShelf Book(Dune) Shelf(Fresh)',
        ], array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'policy '))));
        $this->assertSame(['Book(Dune) [Shelf(Poetry)]', 'Book(Emma) [Shelf(Drama), Shelf(Hidden), Shelf(Poetry)]'], self::rows('books'));
    }
}

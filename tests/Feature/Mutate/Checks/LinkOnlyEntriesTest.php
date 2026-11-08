<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class LinkOnlyEntriesTest extends WriteChecksTestCase
{
    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $writes
     */
    #[Test]
    #[DataProvider('linkOnlyEntries')]
    public function it_writes_no_row_for_an_entry_that_only_links_it(string $endpoint, array $entry, array $writes): void
    {
        $this->mutate($endpoint, [$entry])->assertOk();

        $this->assertSame($writes, self::writes());
    }

    /** @return array<string, array{string, array<string, mixed>, list<string>}> */
    public static function linkOnlyEntries(): array
    {
        $loan = ['hook mutating Loan(Ada)', 'sql update loans set book_id = ?, updated_at = ? where id = ?', 'saved Loan(Ada)', 'hook mutated Loan(Ada)'];

        return [
            'an owner attach' => ['/api/loans/mutate', ['operation' => 'update', 'key' => 101, 'relations' => ['book' => ['operation' => 'attach', 'key' => 102]]], $loan],
            'an owner detach' => ['/api/loans/mutate', ['operation' => 'update', 'key' => 101, 'relations' => ['book' => ['operation' => 'detach', 'key' => 101]]], $loan],
            'a pivot attach' => ['/api/books/mutate', self::shelves(['operation' => 'attach', 'key' => [102]]), self::book('sql insert into book_shelf (book_id, shelf_id) values (?, ?)')],
            'a sync' => ['/api/books/mutate', self::shelves(['operation' => 'sync', 'key' => [102]]), self::book('sql delete from book_shelf where book_shelf.book_id = ? and book_shelf.shelf_id in (?)', 'sql insert into book_shelf (book_id, shelf_id) values (?, ?)')],
            'a toggle' => ['/api/books/mutate', self::shelves(['operation' => 'toggle', 'key' => [101]]), self::book('sql delete from book_shelf where book_shelf.book_id = ? and book_shelf.shelf_id in (?)')],
        ];
    }

    #[Test]
    public function it_saves_a_moved_child_without_running_its_hooks(): void
    {
        $this->mutate('/api/books/mutate', [['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 102]]]])->assertOk();

        $this->assertSame([
            'hook mutating Book(Emma)',
            'saved Book(Emma)',
            'sql update loans set book_id = ?, updated_at = ? where id = ?',
            'saved Loan(Grace)',
            'hook mutated Book(Emma)',
        ], self::writes());
        $this->assertSame(['Loan(Ada) Book(Dune)', 'Loan(Grace) Book(Emma)'], self::rows('loans'));
    }

    /**
     * @param  array<string, mixed>  $book
     * @param  list<string>  $writes
     * @param  list<string>  $books
     */
    #[Test]
    #[DataProvider('linkEntriesWithRelations')]
    public function it_runs_the_hooks_of_a_link_entry_whose_relations_change(array $book, array $writes, array $books): void
    {
        $this->mutate('/api/loans/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['book' => $book]]])->assertOk();

        $this->assertSame($writes, self::writes());
        $this->assertSame($books, self::rows('books'));
    }

    /** @return array<string, array{array<string, mixed>, list<string>, list<string>}> */
    public static function linkEntriesWithRelations(): array
    {
        $loan = ['hook mutating Loan(Ada)', 'sql update loans set book_id = ?, updated_at = ? where id = ?', 'saved Loan(Ada)', 'hook mutated Loan(Ada)'];

        return [
            'an attach with a nested create' => [
                ['operation' => 'attach', 'key' => 102, 'relations' => ['shelves' => [['operation' => 'create', 'attributes' => ['name' => 'Essays']], ['operation' => 'attach', 'key' => [101]]]]],
                [
                    'hook mutating Book(Emma)',
                    'hook mutating Shelf* []',
                    'sql insert into shelves (name, updated_at, created_at) values (?, ?, ?)',
                    'saved Shelf(Essays)',
                    'hook mutated Shelf(Essays)',
                    'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
                    'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
                    'hook mutated Book(Emma)',
                    ...$loan,
                ],
                ['Book(Dune) [Shelf(Classics)]', 'Book(Emma) [Shelf(Classics), Shelf(Drama), Shelf(Essays), Shelf(Hidden), Shelf(Poetry)]'],
            ],
            'a detach with a nested attach' => [
                ['operation' => 'detach', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [102]]]]],
                [
                    'hook mutating Book(Dune)',
                    'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
                    'hook mutated Book(Dune)',
                    ...$loan,
                ],
                ['Book(Dune) [Shelf(Classics), Shelf(Poetry)]', 'Book(Emma) [Shelf(Drama), Shelf(Hidden), Shelf(Poetry)]'],
            ],
        ];
    }

    #[Test]
    public function it_writes_a_sync_target_that_sends_attributes(): void
    {
        $this->mutate('/api/books/mutate', [self::shelves(['operation' => 'sync', 'key' => [102], 'attributes' => ['name' => 'Poems']])])->assertOk();

        $this->assertSame([
            'hook mutating Book(Dune)',
            'saved Book(Dune)',
            'hook mutating Shelf(Poetry)',
            'sql update shelves set name = ?, updated_at = ? where id = ?',
            'saved Shelf(Poems)',
            'hook mutated Shelf(Poems)',
            'sql delete from book_shelf where book_shelf.book_id = ? and book_shelf.shelf_id in (?)',
            'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
            'hook mutated Book(Dune)',
        ], self::writes());
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private static function shelves(array $entry): array
    {
        return ['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [$entry]]];
    }

    /** @return list<string> */
    private static function book(string ...$pivots): array
    {
        return ['hook mutating Book(Dune)', 'saved Book(Dune)', ...$pivots, 'hook mutated Book(Dune)'];
    }
}

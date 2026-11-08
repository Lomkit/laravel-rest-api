<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks;

use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\RowJournal\JournaledBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\RowJournal\JournaledLoanResource;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\RowJournal\JournaledShelfResource;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\RowJournal\JournalEntry;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\RowJournal\RowJournal;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RowJournalTest extends WriteChecksTestCase
{
    private const array MUTATE = [[
        'operation' => 'update',
        'key' => 101,
        'attributes' => ['borrower' => 'Alan'],
        'relations' => ['book' => ['operation' => 'attach', 'key' => 102, 'relations' => ['shelves' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Essays']],
            ['operation' => 'attach', 'key' => [101]],
        ]]]],
    ]];

    #[\Override]
    protected function fixtures(): array
    {
        return [...parent::fixtures(), new RowJournal];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        JournalEntry::$failure = null;
        $this->app?->bind(BookResource::class, JournaledBookResource::class);
        $this->app?->bind(LoanResource::class, JournaledLoanResource::class);
        $this->app?->bind(ShelfResource::class, JournaledShelfResource::class);
    }

    #[Test]
    public function it_lets_mutated_write_one_row_for_each_written_row_inside_the_transaction(): void
    {
        $this->mutate('/api/loans/mutate', self::MUTATE)->assertOk();

        $this->assertSame([
            'begin',
            'hook mutated Shelf(Essays)',
            'sql insert into journal_entries (subject, note) values (?, ?)',
            'hook mutated Book(Emma)',
            'sql insert into journal_entries (subject, note) values (?, ?)',
            'hook mutated Loan(Alan)',
            'sql insert into journal_entries (subject, note) values (?, ?)',
            'commit',
        ], self::journal());
        $this->assertSame(['Shelf(Essays) create', 'Book(Emma) attach shelves', 'Loan(Alan) update book'], JournalEntry::rows());
    }

    /** @return array<string, array{string}> */
    public static function failures(): array
    {
        return [
            'the deepest row' => ['Shelf(Essays)'],
            'the root row, after the rows below it' => ['Loan(Alan)'],
        ];
    }

    #[Test]
    #[DataProvider('failures')]
    public function it_rolls_back_the_request_and_the_rows_that_mutated_wrote_when_it_throws(string $failure): void
    {
        JournalEntry::$failure = $failure;

        $response = $this->mutate('/api/loans/mutate', self::MUTATE);

        $response->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);
        $this->assertSame('rollback', TracingPolicy::$trace[array_key_last(TracingPolicy::$trace)]);
        $this->assertSame([], JournalEntry::rows());
        $this->assertSame(['Loan(Ada) Book(Dune)', 'Loan(Grace) -'], self::rows('loans'));
        $this->assertSame(['Book(Dune) [Shelf(Classics)]', 'Book(Emma) [Shelf(Drama), Shelf(Hidden), Shelf(Poetry)]'], self::rows('books'));
        $this->assertFalse(Shelf::query()->where('name', 'Essays')->exists());
    }

    /** @return list<string> */
    private static function journal(): array
    {
        return array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => preg_match('/^(begin|commit|rollback|hook mutated|sql insert into journal_entries)/', $line) === 1));
    }
}

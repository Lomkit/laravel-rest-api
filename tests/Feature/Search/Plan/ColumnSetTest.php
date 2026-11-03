<?php

namespace Lomkit\Rest\Tests\Feature\Search\Plan;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Search\Plan\Columns\ColumnSet;
use Lomkit\Rest\Tests\Feature\Search\Stubs\CountedLoan;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ColumnSetTest extends PackageTestCase
{
    /** @param Closure(): Builder<Loan> $query */
    #[Test]
    #[DataProvider('selects')]
    public function it_merges_its_columns_with_the_columns_that_the_query_already_selects(Closure $query, string $sql): void
    {
        $builder = $query();

        ColumnSet::of(new Loan, ['borrower', 'id', 'book_id'])->apply($builder);

        $this->assertSame($sql, str_replace(['"', '`'], '', $builder->toRawSql()));
    }

    /** @return array<string, array{Closure(): Builder<Loan>, string}> */
    public static function selects(): array
    {
        $title = static fn (): Builder => Book::query()
            ->select('title')
            ->whereColumn('books.id', 'loans.book_id')
            ->where('title', '!=', 'Emma')
            ->limit(1);

        $titled = static fn (): Builder => Loan::query()
            ->addSelect(['book_title' => $title()])
            ->where('borrower', 'Ada');

        return [
            'no select' => [static fn (): Builder => Loan::query(), 'select loans.borrower, loans.id, loans.book_id from loans'],
            'every column alone' => [static fn (): Builder => Loan::query()->select('loans.*'), 'select loans.borrower, loans.id, loans.book_id from loans'],
            'every column next to a count' => [static fn (): Builder => CountedLoan::query(), 'select loans.borrower, loans.id, loans.book_id, (select count(*) from books where loans.book_id = books.id) as book_count from loans'],
            'every column next to a subquery' => [$titled, "select loans.borrower, loans.id, loans.book_id, (select title from books where books.id = loans.book_id and title != 'Emma' limit 1) as book_title from loans where borrower = 'Ada'"],
            'an unqualified every column' => [static fn (): Builder => Loan::query()->select('*'), 'select loans.borrower, loans.id, loans.book_id from loans'],
            'columns without their table' => [static fn (): Builder => Loan::query()->select('id', 'borrower'), 'select id, borrower, loans.book_id from loans'],
        ];
    }

    #[Test]
    public function it_keeps_only_every_column_when_a_level_needs_every_column(): void
    {
        $this->assertSame(['loans.*'], ColumnSet::of(new Loan, ['borrower', '*', 'id'])->columns);
    }
}

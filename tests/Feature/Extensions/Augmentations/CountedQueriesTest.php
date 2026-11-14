<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Augmentations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Query\CountedQueries;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class CountedQueriesTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[Test]
    public function it_removes_a_bound_subquery_from_a_clone_and_keeps_what_the_clone_adds(): void
    {
        $query = Book::query()->select('books.id')->selectRaw('? as planned', ['kept']);
        self::augment($query);

        $clone = (clone $query)->selectRaw('? as later', ['later'])->where('books.id', '>', 0);
        $counted = CountedQueries::withoutAdditions($clone);

        $this->assertSame('select "books"."id", ? as planned, ? as later from "books" where "books"."id" > ?', $counted->toSql());
        $this->assertSame(['kept', 'later', 0], $counted->getBindings());
        $this->assertSame(['kept', 'Reader 1', 'later', 0], $clone->getBindings());
    }

    #[Test]
    public function it_restores_no_selection_when_the_plan_selected_none(): void
    {
        $query = Book::query();
        self::augment($query);

        $counted = CountedQueries::withoutAdditions($query->clone()->clone());

        $this->assertNull($counted->getQuery()->columns);
        $this->assertSame([], $counted->getBindings());
    }

    #[Test]
    public function it_counts_a_clone_whose_selection_was_replaced_as_it_is(): void
    {
        $query = Book::query()->select('books.id');
        self::augment($query);

        $replaced = (clone $query)->select('books.title');

        $this->assertSame($replaced, CountedQueries::withoutAdditions($replaced));
        $this->assertSame('select "books"."title" from "books"', $replaced->toSql());
    }

    /** @param Builder<Model> $query */
    private static function augment(Builder $query): void
    {
        CountedQueries::augment($query, static function () use ($query): void {
            $query->addSelect(['loans__copies' => Loan::query()->selectRaw('count(*)')->whereColumn('loans.book_id', 'books.id')->where('borrower', '!=', 'Reader 1')]);
        });
    }
}

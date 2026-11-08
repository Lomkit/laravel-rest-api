<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;

#[Table('books')]
#[UsePolicy(OpenLibraryPolicy::class)]
class CappedBook extends Book
{
    /** @param Builder<self> $query */
    #[Scope]
    protected function borrowedTwice(Builder $query): void
    {
        $query
            ->join('loans', 'loans.book_id', '=', 'books.id')
            ->groupBy('books.id')
            ->havingRaw('count(loans.id) >= 2');
    }
}

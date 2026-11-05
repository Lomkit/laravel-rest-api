<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;

#[Table('books')]
#[UsePolicy(OpenLibraryPolicy::class)]
class CountedBook extends Book
{
    /** @return HasMany<CountedLoan, $this> */
    public function countedLoans(): HasMany
    {
        return $this->hasMany(CountedLoan::class, 'book_id');
    }

    /** @return HasMany<Loan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'book_id');
    }
}

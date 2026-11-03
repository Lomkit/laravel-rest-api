<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;

#[Table('loans')]
#[UsePolicy(OpenLibraryPolicy::class)]
class ScopedLoan extends Loan
{
    #[\Override]
    protected static function booted(): void
    {
        static::addGlobalScope('book', fn (Builder $query): Builder => $query->with('book'));
    }
}

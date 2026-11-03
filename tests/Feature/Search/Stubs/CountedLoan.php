<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;

#[Table('loans')]
#[UsePolicy(OpenLibraryPolicy::class)]
class CountedLoan extends Loan
{
    /** @var list<string> */
    protected $withCount = ['book'];
}

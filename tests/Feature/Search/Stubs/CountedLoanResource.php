<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;

class CountedLoanResource extends LoanResource
{
    public static string $model = CountedLoan::class;
}

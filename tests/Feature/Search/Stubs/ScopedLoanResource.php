<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;

class ScopedLoanResource extends LoanResource
{
    public static string $model = ScopedLoan::class;
}

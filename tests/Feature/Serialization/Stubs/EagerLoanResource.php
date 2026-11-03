<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;

class EagerLoanResource extends LoanResource
{
    public static string $model = EagerLoan::class;
}

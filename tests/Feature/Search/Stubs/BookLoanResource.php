<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;

class BookLoanResource extends LoanResource
{
    #[\Override]
    public function instructions(RestRequest $request): array
    {
        return [WithBook::make(), BookTitle::make()];
    }
}

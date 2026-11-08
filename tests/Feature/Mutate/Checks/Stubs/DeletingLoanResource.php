<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;

class DeletingLoanResource extends LoanResource
{
    use TracesDestroyHooks;
    use TracesHooks;

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('loans.borrower', '<>', 'Hidden');
    }
}

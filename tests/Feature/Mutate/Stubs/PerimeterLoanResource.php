<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;

class PerimeterLoanResource extends LoanResource
{
    use HidesNamedRows;

    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('book', PerimeterBookResource::class)];
    }

    protected static function nameColumn(): string
    {
        return 'loans.borrower';
    }
}

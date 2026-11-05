<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;

class TalliedLoanResource extends LoanResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('book', TalliedBookResource::class)];
    }
}

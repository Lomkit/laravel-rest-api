<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;

class LinkCheckedLoanResource extends LoanResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('book', BookResource::class)->abilities(attach: 'attachBook', detach: 'detachBook')];
    }
}

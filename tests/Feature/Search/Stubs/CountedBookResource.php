<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class CountedBookResource extends BookResource
{
    public static string $model = CountedBook::class;

    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('countedLoans', CountedLoanResource::class),
            HasMany::make('loans', BookLoanResource::class),
        ];
    }
}

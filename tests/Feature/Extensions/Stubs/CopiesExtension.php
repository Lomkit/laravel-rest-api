<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\AugmentationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AugmentsQueries;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;

final class CopiesExtension extends Extension implements AugmentsQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'loans';
    }

    #[\Override]
    public function augment(AugmentationContext $context): void
    {
        $context->select('copies', Loan::query()->selectRaw('count(*)')->whereColumn('loans.book_id', 'books.id'));
    }
}

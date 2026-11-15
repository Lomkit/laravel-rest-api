<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

final class JoiningRowsExtension extends Extension implements RestrictsQueries
{
    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->join('voucher_lines', 'voucher_lines.voucher_id', '=', $context->query->qualifyColumn('id'));
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;

class RedRowsExtension extends Extension implements RestrictsQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'red';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return is_a($resource::$model, Voucher::class, true);
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        RestrictionLog::$seen[] = class_basename($context->resource);
        RestrictionLog::$users[] = $context->user;

        $context->query->where($context->query->qualifyColumn('team'), 'red');
    }
}

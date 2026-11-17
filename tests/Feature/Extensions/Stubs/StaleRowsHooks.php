<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\ReadObservationContext;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Extensions\Kinds\ObservesReads;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;

class StaleRowsHooks extends Extension implements AddsSearchMeta, ObservesReads
{
    #[\Override]
    public static function name(): string
    {
        return 'rows';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === StaleVoucherResource::class;
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        EventLog::record('meta '.VoucherRows::describe($context->rows));

        return ['rows' => VoucherRows::describe($context->rows)];
    }

    #[\Override]
    public function observeRead(ReadObservationContext $context): void
    {
        EventLog::record('read '.VoucherRows::describe($context->rows ?? []));
    }
}

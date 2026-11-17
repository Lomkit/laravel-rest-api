<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\LockAllowedVouchers;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\LockVouchers;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\QueuedRetitleVouchers;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Fixtures\Ledger\EitherTeamVoucherResource;

class StaleVoucherResource extends EitherTeamVoucherResource
{
    #[\Override]
    public function actions(): array
    {
        return [QueuedRetitleVouchers::class, BatchedRetitleVouchers::class, LockVouchers::class, LockAllowedVouchers::class];
    }

    #[\Override]
    public function searched(LengthAwarePaginator $page): void
    {
        EventLog::record('searched '.VoucherRows::describe($page->items()));
    }
}

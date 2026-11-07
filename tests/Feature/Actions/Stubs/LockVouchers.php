<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class LockVouchers extends Action
{
    public int $chunkCount = 2;

    #[\Override]
    public function authorizeEach(): ?string
    {
        return 'update';
    }

    /** @param Collection<int, Voucher> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        app(ReceivedFields::class)->record('handle', ['ids' => $models->modelKeys()]);
    }
}

<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class RetitleVouchers extends Action
{
    public int $chunkCount = 2;

    /** @param Collection<int, Voucher> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        app(ReceivedFields::class)->record('handle', ['ids' => $models->pluck('id')->all()]);

        foreach ($models as $voucher) {
            $voucher->update(['title' => "Renamed {$voucher->code}"]);
        }
    }
}

<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class DeactivateVouchers extends Action
{
    /** @param Collection<int, Voucher> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        app(ReceivedFields::class)->record('handle', ['ids' => $models->pluck('id')->all()]);

        foreach ($models as $voucher) {
            $voucher->update(['active' => false]);
        }
    }
}

<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class DeactivateVouchers extends Action
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Voucher>  $models
     */
    #[\Override]
    public function handle(array $fields, Collection $models): void
    {
        app(ReceivedFields::class)->record('handle', ['ids' => $models->pluck('id')->all()]);

        foreach ($models as $voucher) {
            $voucher->update(['active' => false]);
        }
    }
}

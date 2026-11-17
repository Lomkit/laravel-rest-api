<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\RowCount;

class MeasuredListedVoucherResource extends ListedVoucherResource
{
    #[\Override]
    public function actions(): array
    {
        return [...parent::actions(), BatchedDeactivateVouchers::make()];
    }

    #[\Override]
    public function metrics(): array
    {
        return [RowCount::make()];
    }
}

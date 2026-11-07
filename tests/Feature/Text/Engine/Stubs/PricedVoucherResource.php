<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;

class PricedVoucherResource extends VoucherResource
{
    /** @var list<mixed> */
    public static array $prices = [];

    #[\Override]
    public function searched(LengthAwarePaginator $page): void
    {
        foreach ($page->items() as $voucher) {
            if ($voucher instanceof Model) {
                self::$prices[] = $voucher->getAttribute('price');
            }
        }
    }
}

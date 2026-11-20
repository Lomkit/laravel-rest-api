<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Lomkit\Rest\Tests\Fixtures\Ledger\OpenLedgerPolicy;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;

#[Table('vouchers')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class CentsVoucher extends Voucher
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toSearchableArray(): array
    {
        $document = parent::toSearchableArray();

        return array_key_exists('price', $document) ? [...$document, 'price' => (float) $document['price']] : $document;
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [...parent::casts(), 'price' => 'decimal:2'];
    }
}

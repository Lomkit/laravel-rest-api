<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Engines\DatabaseEngine;

#[Table('vouchers')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class ReaderVoucher extends Voucher
{
    public const array READERS = [
        'V-a' => ['ann', 'bob'],
        'V-b' => ['ann'],
        'V-c' => ['bob'],
        'V-d' => ['cid'],
        'V-e' => [],
        'V-f' => ['ann'],
    ];

    /** @return array<string, mixed> */
    #[\Override]
    public function toSearchableArray(): array
    {
        $document = parent::toSearchableArray();
        $engine = $this->searchableUsing();

        if ($engine instanceof DatabaseEngine || $engine instanceof CollectionEngine) {
            return $document;
        }

        return [...$document, 'readers' => self::READERS[$this->code] ?? []];
    }
}

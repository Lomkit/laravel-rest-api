<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Grouped;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Ledger\OpenLedgerPolicy;

#[Table('voucher_lines')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class GroupedLine extends Model
{
    /** @return BelongsTo<GroupedVoucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(GroupedVoucher::class, 'voucher_id');
    }
}

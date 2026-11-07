<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Contracts\Database\Eloquent\Builder;

class EitherTeamVoucherResource extends VoucherResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('team', 'red')->orWhere('team', 'blue');
    }
}

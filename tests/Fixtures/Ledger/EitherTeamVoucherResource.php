<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

class EitherTeamVoucherResource extends VoucherResource
{
    #[\Override]
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('team', 'red')->orWhere('team', 'blue');
    }
}

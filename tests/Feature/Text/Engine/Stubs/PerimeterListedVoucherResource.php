<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;

class PerimeterListedVoucherResource extends OpenListedVoucherResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('team', 'red');
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;

class UnselectableTeamVoucherResource extends Resource
{
    public static string $model = Voucher::class;

    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('id'),
            Text::make('code')->selectable(false)->filterable(),
            Text::make('team')->selectable(false),
        ];
    }
}

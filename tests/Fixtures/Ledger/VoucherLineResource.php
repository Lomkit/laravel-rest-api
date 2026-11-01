<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;

class VoucherLineResource extends Resource
{
    public static string $model = VoucherLine::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Integer::make('voucher_id'), Text::make('label')];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [BelongsTo::make('voucher', VoucherResource::class)];
    }
}

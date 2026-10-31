<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

class ContainsVoucherResource extends VoucherResource
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('code'), Text::make('title')->searchable()];
    }

    #[\Override]
    public function text(RestRequest $request): ?TextSearch
    {
        return Contains::make();
    }
}

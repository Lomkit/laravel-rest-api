<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class GrammarlessVoucherResource extends VoucherResource
{
    #[\Override]
    public function text(RestRequest $request): ?TextSearch
    {
        $engine = ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->withoutPerimeter();

        return new GrammarlessEngineSearch($engine);
    }
}

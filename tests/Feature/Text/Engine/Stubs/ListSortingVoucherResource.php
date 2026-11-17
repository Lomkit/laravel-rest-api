<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Text\TextSearch;

class ListSortingVoucherResource extends PlainEngineVoucherResource
{
    #[\Override]
    public function text(): ?TextSearch
    {
        return new ListSortingEngineSearch;
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

class OrderedListedVoucherResource extends OpenListedVoucherResource
{
    #[\Override]
    public function defaultOrderBy(): array
    {
        return ['number' => 'asc'];
    }
}

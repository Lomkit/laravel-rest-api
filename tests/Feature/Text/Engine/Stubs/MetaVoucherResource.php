<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;

class MetaVoucherResource extends VoucherResource
{
    #[\Override]
    public function instructions(): array
    {
        return [NotedNumberedFrom::make()];
    }

    #[\Override]
    public function searchMeta(SearchContext $search, LengthAwarePaginator $page): array
    {
        return [
            'text' => $search->text?->value,
            'numbers' => array_sum(array_map(fn (Voucher $voucher): int => $voucher->number, $page->items())),
        ];
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

final class MetaTicketResource extends TicketResource
{
    #[\Override]
    public function searchMeta(SearchContext $search, LengthAwarePaginator $page): array
    {
        return MetaLog::$searchMeta ?? ['count' => count($page->items())];
    }

    #[\Override]
    public function detailsMeta(): array
    {
        return MetaLog::$detailsMeta ?? ['plan' => 'pro'];
    }
}

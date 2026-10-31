<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class TeamVoucherResource extends VoucherResource
{
    #[\Override]
    public function text(RestRequest $request): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->sortable(['price', 'number'])
            ->perimeter(fn (EngineQuery $query, RestRequest $request): EngineQuery => $query->whereIn('team', self::teams($request)), [Text::make('team')]);
    }

    #[\Override]
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->whereIn('team', self::teams($request));
    }

    /** @return list<string> */
    private static function teams(RestRequest $request): array
    {
        return $request->user()?->teams ?? [];
    }
}

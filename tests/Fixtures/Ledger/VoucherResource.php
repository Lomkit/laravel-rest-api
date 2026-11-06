<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class VoucherResource extends Resource
{
    public static string $model = Voucher::class;

    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('id'),
            Text::make('code'),
            Integer::make('number'),
            Text::make('team'),
            Text::make('title'),
            Number::make('price'),
            Boolean::make('active'),
        ];
    }

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('lines', VoucherLineResource::class)];
    }

    #[\Override]
    public function scopes(): array
    {
        return [Scope::make('cheap')];
    }

    #[\Override]
    public function actions(): array
    {
        return [
            DeactivateVouchers::make(),
            RetitleVouchers::make(),
            ReviewVouchers::make(),
            QueuedReviewVouchers::make(),
            BatchedReviewVouchers::make(),
        ];
    }

    #[\Override]
    public function instructions(): array
    {
        return [PricedUpTo::make(), NumberedFrom::make(), WithLines::make()];
    }

    #[\Override]
    public function limits(): array
    {
        return [2, 10, 50];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code'])
            ->sortable(['price', 'number'])
            ->withoutPerimeter();
    }

    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('team', 'red');
    }
}

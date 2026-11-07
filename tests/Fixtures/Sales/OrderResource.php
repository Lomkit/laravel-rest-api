<?php

namespace Lomkit\Rest\Tests\Fixtures\Sales;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

class OrderResource extends Resource
{
    public static string $model = Order::class;

    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('id'),
            Integer::make('team_id'),
            Text::make('reference')->searchable(),
            Enum::make('status')->nullable(),
            Text::make('channel')->nullable(),
            Text::make('customer')->nullable(),
            Number::make('price'),
            Integer::make('quantity'),
            Integer::make('rating')->nullable(),
            Boolean::make('gift'),
            DateTime::make('placed_at'),
            Date::make('delivered_on')->nullable(),
            Text::make('summary')
                ->virtual()
                ->resolveUsing(fn (Order $order): string => $order->reference),
        ];
    }

    #[\Override]
    public function text(): TextSearch
    {
        return Contains::make();
    }

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('orders.team_id', 1);
    }
}

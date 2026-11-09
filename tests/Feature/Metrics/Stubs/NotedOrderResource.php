<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Text;

class NotedOrderResource extends MetricOrderResource
{
    #[\Override]
    public function fields(): array
    {
        return [
            ...parent::fields(),
            Text::make('note')
                ->virtual()
                ->filterUsing(fn (Builder $query, string $operator, mixed $value): Builder => $query),
        ];
    }
}

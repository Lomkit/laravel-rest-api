<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Stubs;

use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class ConditionalPivotTicketResource extends TicketResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            BelongsToMany::make('tags', WeightedTagResource::class)->pivotFields([
                Integer::make('position')->nullable(),
                Integer::make('weight')
                    ->nullable()
                    ->rules(Rule::when(fn (Fluent $pivot): bool => $pivot->get('position') === 1, ['required'])),
            ]),
        ];
    }
}

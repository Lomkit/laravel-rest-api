<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Text;

class SummarizedTicketResource extends PartialTicketResource
{
    #[\Override]
    public function fields(): array
    {
        return [
            ...parent::fields(),
            Text::make('summary')
                ->virtual()
                ->resolveUsing(fn (Model $ticket): string => $ticket->getAttribute('title').' ('.$ticket->getAttribute('status').')'),
        ];
    }
}

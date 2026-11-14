<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class PreparedTicketResource extends TicketResource
{
    #[\Override]
    public function prepareAuthorization(string $ability, Collection $rows): void
    {
        $user = $this->request()->user()?->getAuthIdentifier() ?? 'guest';

        app(TicketPolicy::class)->calls[] = 'prepare '.$ability.' '.implode(',', $rows->modelKeys()).' for '.$user;
    }

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsTo::make('team', PreparedTeamResource::class),
            BelongsToMany::make('tags', TagResource::class)
                ->pivotFields([Integer::make('position')->nullable()])
                ->abilities(attach: 'attachTag', detach: 'detachTag'),
        ];
    }

    #[\Override]
    public function actions(): array
    {
        return [ReviewTickets::make()->target(ActionTarget::Resources)];
    }
}

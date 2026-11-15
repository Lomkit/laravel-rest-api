<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Closure;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class PreparedTicketResource extends TicketResource
{
    /** @var list<PendingChecks> */
    public static array $received = [];

    /** @var (Closure(PendingChecks): void)|null */
    public static ?Closure $prepare = null;

    #[\Override]
    public function prepareAuthorization(PendingChecks $checks): void
    {
        self::$received[] = $checks;
        $user = $checks->user?->getAuthIdentifier() ?? 'guest';

        app(TicketPolicy::class)->calls[] = 'prepare '.$checks->ability.' '.implode(',', $checks->rows->modelKeys()).' for '.$user;

        if (self::$prepare !== null) {
            (self::$prepare)($checks);
        }
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
        return [
            EscalateTickets::make()->target(ActionTarget::Resources),
            EscalateAllowedTickets::make()->target(ActionTarget::Resources)->skipUnauthorized(),
        ];
    }
}

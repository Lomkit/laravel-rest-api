<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use DateTimeInterface;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Changes;

class ArchivedTicketResource extends TicketResource
{
    /** @param list<Model|Changes> $arguments */
    #[\Override]
    public function authorizedTo(string $ability, Model|string $model, array $arguments = []): Response
    {
        return match ($ability) {
            'delete' => Response::deny('Tickets are archived, not deleted.'),
            'create' => Response::deny('Tickets are archived, not created.'),
            default => parent::authorizedTo($ability, $model, $arguments),
        };
    }

    #[\Override]
    public function cacheAuthorizationFor(): DateTimeInterface
    {
        return now()->addHour();
    }
}

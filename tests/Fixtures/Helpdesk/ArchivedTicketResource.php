<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use DateTimeInterface;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class ArchivedTicketResource extends TicketResource
{
    public function authorizedTo(string $ability, Model|string $model): Response
    {
        return match ($ability) {
            'delete' => Response::deny('Tickets are archived, not deleted.'),
            'create' => Response::deny('Tickets are archived, not created.'),
            default => parent::authorizedTo($ability, $model),
        };
    }

    public function cacheAuthorizationFor(): DateTimeInterface
    {
        return now()->addHour();
    }
}

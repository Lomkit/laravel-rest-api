<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class TicketPolicy extends RecordingPolicy
{
    public const int LEAD = 1;

    public bool $open = true;

    #[\Override]
    public function viewAny(?Authenticatable $user): bool|Response
    {
        $answer = $this->record('viewAny');

        return $this->open ? $answer : Response::denyWithStatus(423, 'The helpdesk is closed.');
    }

    #[\Override]
    public function update(?Authenticatable $user, Model $model): Response
    {
        $this->record('update '.$model->getKey());

        return $model->getAttribute('status') === 'closed'
            ? Response::denyWithStatus(423, 'Closed tickets are read-only.')
            : Response::allow();
    }

    #[\Override]
    public function delete(?Authenticatable $user, Model $model): Response
    {
        $this->record('delete '.$model->getKey());

        return $user?->getAuthIdentifier() === self::LEAD ? Response::allow() : Response::deny('Only the helpdesk lead deletes tickets.');
    }

    public function attachTag(?Authenticatable $user, Ticket $ticket, Tag $tag): Response
    {
        $this->record('attachTag '.$ticket->getKey().' '.$tag->getKey());

        return $tag->getAttribute('name') === 'Urgent'
            ? Response::denyWithStatus(409, 'Urgent tickets are flagged by the escalation desk.')
            : Response::allow();
    }

    public function detachTag(?Authenticatable $user, Ticket $ticket, Tag $tag): Response
    {
        $this->record('detachTag '.$ticket->getKey().' '.$tag->getKey());

        return $tag->getAttribute('name') === 'Urgent'
            ? Response::denyWithStatus(409, 'Urgent tickets are cleared by the escalation desk.')
            : Response::allow();
    }
}

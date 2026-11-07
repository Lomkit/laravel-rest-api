<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

class KeptTicketPolicy extends TicketPolicy
{
    #[\Override]
    public function delete(?Authenticatable $user, Model $model): Response
    {
        $this->record('delete '.$model->getKey());

        return $model->getAttribute('status') === 'closed' ? Response::deny('Closed tickets are kept.') : Response::allow();
    }
}

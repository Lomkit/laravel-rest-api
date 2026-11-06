<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

class ViewOnlyTicketPolicy extends TicketPolicy
{
    #[\Override]
    public function update(?Authenticatable $user, Model $model): Response
    {
        return Response::deny();
    }
}

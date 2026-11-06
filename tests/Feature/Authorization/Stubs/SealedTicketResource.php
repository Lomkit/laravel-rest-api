<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Changes;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;

class SealedTicketResource extends CachedTicketResource
{
    /** @param list<Model|Changes> $arguments */
    #[\Override]
    public function authorizedTo(string $ability, Model|string $model, array $arguments = []): Response
    {
        if ($ability === 'delete' && $this->request()->hasHeader('X-Sealed')) {
            return Response::deny('Sealed tickets are kept.');
        }

        return parent::authorizedTo($ability, $model, $arguments);
    }
}

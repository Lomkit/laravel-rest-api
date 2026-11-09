<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

class PrivateTagResource extends TagResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        if ($this->request()->user()?->getAuthIdentifier() === TicketPolicy::LEAD) {
            return $query;
        }

        return $query->where('tags.name', '!=', 'Secret');
    }
}

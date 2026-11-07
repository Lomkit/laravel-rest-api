<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class TrashedTagResource extends TagResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->withTrashed();
    }
}

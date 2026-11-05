<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class TrashedTagResource extends TagResource
{
    #[\Override]
    public function mutateQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->withTrashed();
    }
}

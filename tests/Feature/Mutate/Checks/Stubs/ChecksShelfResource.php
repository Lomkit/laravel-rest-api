<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class ChecksShelfResource extends ShelfResource
{
    use TracesHooks;

    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('shelves.name', '!=', 'Hidden');
    }
}

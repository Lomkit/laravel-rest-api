<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

class OrPerimeterModelResource extends ModelResource
{
    /**
     * A perimeter shaped like the common "mine or shared" tenant rule, whose first branch also
     * stops matching once ModifyNumberAction has run on a model.
     */
    public function searchQuery(RestRequest $request, Builder $query)
    {
        $query = parent::searchQuery($request, $query);

        $query->where('number', '<', 100000000)->orWhere('string', 'public');

        return $query;
    }
}

<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\Traits\ParsesResourceKeys;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Validation\KeyList;

class DestroyRequest extends RestRequest
{
    use ParsesResourceKeys;

    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Destroy;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        $this->resource($resource = $this->context()->resource($this->controllerResourceClass()));

        return [
            ...$this->resourceKeyRules($resource, $this->context()->limits()->sizes->resources),
            'search' => ['bail', 'prohibited', 'array'],
            'confirmation' => ['string'],
        ];
    }

    public function payload(): KeyList
    {
        return $this->resourceKeys();
    }

    /** @return EloquentBuilder<Model> */
    #[\Override]
    public function keysQuery(Resource $resource): EloquentBuilder
    {
        $query = $resource::newModel()->newQuery();

        (new PerimeterClause($resource))->apply($query);

        return $query;
    }
}

<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Lomkit\Rest\Http\Requests\Traits\ParsesResourceKeys;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Validation\KeyList;

class ForceDestroyRequest extends RestRequest
{
    use ParsesResourceKeys;

    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::ForceDestroy;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        $this->resource($resource = $this->context()->resource($this->controllerResourceClass()));

        return [
            ...$this->resourceKeyRules($resource, $this->context()->limits()->sizes->resources),
            'search' => ['bail', 'prohibited', 'array'],
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
        return $resource::newModel()
            ->newQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where(fn (Builder $query): Builder => $resource->perimeterQuery($this, $query))
            ->where(fn (Builder $query): Builder => $resource->forceDestroyQuery($this, $query));
    }
}

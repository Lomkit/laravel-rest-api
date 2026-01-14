<?php

namespace Lomkit\Rest\Query\Operators;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class Instruction implements Operator
{
    public function __construct(
        protected string $name,
        protected array $fields = [],
    ) {
    }

    public function handle(Builder $query, Resource $resource): Builder
    {
        $resource->instruction(app(RestRequest::class), $this->name)
            ->handle(
                collect($this->fields)->mapWithKeys(function ($field) {return [$field['name'] => $field['value']]; })->toArray(),
                $query
            );

        return $query;
    }
}

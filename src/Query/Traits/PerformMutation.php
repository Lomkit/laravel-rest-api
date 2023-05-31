<?php

namespace Lomkit\Rest\Query\Traits;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformMutation
{
    public function mutate(array $parameters = []) {
        // @TODO: work here

        $this->when(isset($parameters['create']), function () use ($parameters) {

        });

        $this->when(isset($parameters['attach']), function () use ($parameters) {

        });

        $this->when(isset($parameters['update']), function () use ($parameters) {

        });

        return $this->queryBuilder;
    }
}
<?php

namespace Lomkit\Rest\Query;

use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Resource;

final readonly class QueryBuilderFactory
{
    public static function make(Resource $resource): QueryBuilder
    {
        return app()->make(QueryBuilder::class, ['resource' => $resource, 'query' => null]);
    }
}

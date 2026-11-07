<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\Plan\Columns\ColumnSelection;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\Plan\SqlRows;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\ResolvedText;

final readonly class MetricQuery
{
    /** @return Builder<Model> */
    public static function of(Resource $resource, SearchNode $search, ResolvedText $text): Builder
    {
        $plan = (new SearchPlanner(new SqlRows($text, ordered: false), ColumnSelection::Every))->plan($resource, $search, LimitPolicy::none());

        return $plan->query()->reorder();
    }
}

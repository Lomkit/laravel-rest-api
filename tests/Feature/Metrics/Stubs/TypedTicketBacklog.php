<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\MetricResult;

class TypedTicketBacklog extends TicketBacklog
{
    #[\Override]
    public function calculate(Builder $query): MetricResult
    {
        return new Values(['type' => 'count', 'value' => $query->count()]);
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricResult;
use stdClass;

class TicketsByStatus extends Metric
{
    #[\Override]
    public function type(): string
    {
        return 'breakdown';
    }

    #[\Override]
    public function calculate(Builder $query): MetricResult
    {
        $groups = $query->toBase()
            ->select('tickets.status')
            ->selectRaw('count(*) as total')
            ->groupBy('tickets.status')
            ->orderBy('tickets.status')
            ->get();

        return new Values(['groups' => $groups->map(fn (stdClass $group): array => ['status' => $group->status, 'total' => $group->total])->all()]);
    }
}

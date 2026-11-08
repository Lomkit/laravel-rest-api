<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricResult;

class Occupancy extends Metric
{
    #[\Override]
    public function type(): string
    {
        return 'occupancy';
    }

    #[\Override]
    public function calculate(Builder $query): MetricResult
    {
        return new OccupancyResult((int) $query->sum('seats'), 1000);
    }

    #[\Override]
    public function schema(): ?Type
    {
        $schema = new ObjectType;
        $schema->addProperty('seats', new IntegerType);
        $schema->addProperty('capacity', new IntegerType);
        $schema->setRequired(['seats', 'capacity']);

        return $schema;
    }
}

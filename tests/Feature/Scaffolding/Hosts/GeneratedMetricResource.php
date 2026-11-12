<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Hosts;

use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Fixtures\Sales\OrderResource;

class GeneratedMetricResource extends OrderResource
{
    /** @var list<Metric|class-string<Metric>> */
    public static array $declared = [];

    #[\Override]
    public function fields(): array
    {
        return [
            ...parent::fields(),
            DateTime::make('created_at'),
        ];
    }

    #[\Override]
    public function metrics(): array
    {
        return self::$declared;
    }
}

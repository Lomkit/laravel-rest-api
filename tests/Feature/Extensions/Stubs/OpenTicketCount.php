<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Value;
use Lomkit\Rest\Metrics\ValueResult;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;

final class OpenTicketCount extends Value
{
    #[\Override]
    public function calculate(Builder $query): ValueResult
    {
        EventLog::record('calculate');

        return $this->count($query->where('status', 'open'));
    }
}

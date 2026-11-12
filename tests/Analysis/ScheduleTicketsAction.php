<?php

namespace Lomkit\Rest\Tests\Analysis;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;

final class ScheduleTicketsAction extends Action
{
    public function __construct(private readonly Repository $cache) {}

    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        $this->schedule($fields->date('starts_on'), $fields->enum('status', TicketStatus::class));
    }

    private function schedule(?CarbonImmutable $startsOn, ?TicketStatus $status): void
    {
        $this->cache->put('tickets.schedule', [$startsOn?->toDateString(), $status?->value]);
    }
}

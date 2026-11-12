<?php

namespace Lomkit\Rest\Tests\Analysis;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;

final class PlanTicketsAction extends Action
{
    public function __construct(private readonly Repository $cache) {}

    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        $this->plan($fields->date('starts_on', required: true), $fields->enum('status', TicketStatus::class, required: true));
    }

    private function plan(CarbonImmutable $startsOn, TicketStatus $status): void
    {
        $this->cache->put('tickets.plan', [$startsOn->toDateString(), $status->value]);
    }
}

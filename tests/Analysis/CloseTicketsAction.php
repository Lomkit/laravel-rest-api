<?php

namespace Lomkit\Rest\Tests\Analysis;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;

final class CloseTicketsAction extends Action
{
    public function __construct(private readonly Repository $cache) {}

    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        $this->cache->put('tickets.closing_reason', $fields->reason);
    }
}

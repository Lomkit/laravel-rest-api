<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;

class WatchTickets extends Action
{
    #[\Override]
    public function abilities(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Ticket>  $models
     */
    #[\Override]
    public function handle(array $fields, Collection $models): void {}
}

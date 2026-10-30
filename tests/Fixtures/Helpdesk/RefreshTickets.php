<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;

class RefreshTickets extends Action
{
    public bool $standalone = true;

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Ticket>  $models
     */
    public function handle(array $fields, Collection $models): void {}
}

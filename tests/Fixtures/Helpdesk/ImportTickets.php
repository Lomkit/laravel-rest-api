<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;

class ImportTickets extends Action
{
    public bool $standalone = true;

    #[\Override]
    public function abilities(): array
    {
        return ['create'];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Ticket>  $models
     */
    #[\Override]
    public function handle(array $fields, Collection $models): void {}
}

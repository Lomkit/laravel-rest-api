<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTarget;

class ImportTickets extends Action
{
    public ActionTarget $target = ActionTarget::None;

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

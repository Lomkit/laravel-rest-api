<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;

class ReviewTicketsPerRow extends Action
{
    public int $chunkCount = 2;

    #[\Override]
    public function authorizeEach(): ?string
    {
        return ReviewTickets::$ability;
    }

    #[\Override]
    public function handleRow(ActionFields $fields, Model $row): void
    {
        ReviewTickets::$runs[] = [$row->getKey()];
    }
}

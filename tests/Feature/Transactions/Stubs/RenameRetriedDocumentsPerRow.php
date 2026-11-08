<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;

class RenameRetriedDocumentsPerRow extends Action
{
    public int $chunkCount = 2;

    #[\Override]
    public function beforeRow(ActionFields $fields, Model $row): void
    {
        Interruptions::$runs[] = "before {$row->getKey()}";
    }

    #[\Override]
    public function handleRow(ActionFields $fields, Model $row): void
    {
        $row->update(['title' => 'Renamed']);

        Interruptions::run("row {$row->getKey()}");
    }
}

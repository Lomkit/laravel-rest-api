<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;

final class ArchiveEachDocument extends Action
{
    public int $chunkCount = 1;

    #[\Override]
    public function beforeRow(ActionFields $fields, Model $row): void
    {
        EventLog::record("before {$row->getKey()}");
    }

    #[\Override]
    public function handleRow(ActionFields $fields, Model $row): void
    {
        EventLog::record("row {$row->getKey()}");
        $row->update(['archived' => true]);
    }
}

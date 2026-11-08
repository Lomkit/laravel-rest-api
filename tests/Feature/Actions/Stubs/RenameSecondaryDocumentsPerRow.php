<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use RuntimeException;

class RenameSecondaryDocumentsPerRow extends Action
{
    /** @var list<string> */
    public static array $runs = [];

    public int $chunkCount = 2;

    #[\Override]
    public function beforeRow(ActionFields $fields, Model $row): void
    {
        self::$runs[] = "before {$row->getKey()} at level ".DB::connection('secondary')->transactionLevel();
    }

    #[\Override]
    public function handleRow(ActionFields $fields, Model $row): void
    {
        self::$runs[] = "row {$row->getKey()} at level ".DB::connection('secondary')->transactionLevel();

        if ($row->getAttribute('title') === 'Budget') {
            throw new RuntimeException('The budget cannot be renamed.');
        }

        $row->update(['title' => 'Renamed']);
    }
}

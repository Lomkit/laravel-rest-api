<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Mutations\MutationNode;

class JournaledDocumentResource extends RetriedDocumentResource
{
    #[\Override]
    public function mutated(MutationNode $entry, Model $model): void
    {
        self::record("{$entry->operation->value} {$model->getAttribute('title')}");
    }

    #[\Override]
    public function destroyed(Model $model): void
    {
        self::record("destroy {$model->getAttribute('title')}");
    }

    private static function record(string $note): void
    {
        DB::connection('secondary')
            ->table('journal')
            ->insert(['note' => $note]);

        Interruptions::run($note);
    }
}

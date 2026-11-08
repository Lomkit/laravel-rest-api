<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\ActionFields;

class RowTransactionWithHandle extends RenameSecondaryDocumentsPerRow
{
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void {}
}

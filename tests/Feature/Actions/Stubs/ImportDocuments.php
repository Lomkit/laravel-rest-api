<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Confirmation\Confirmation;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;

class ImportDocuments extends Action
{
    public ActionTarget $target = ActionTarget::None;

    #[\Override]
    public function confirmation(ActionFields $fields, int $rows): Confirmation
    {
        ShredDocuments::$asked[] = $rows;

        return Confirmation::make('Import the documents?');
    }

    /** @param Collection<int, Document> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        ShredDocuments::$runs[] = $models->modelKeys();
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionTarget;

class RetitleUntypedDrafts extends Action
{
    public ActionTarget $target = ActionTarget::Resources;

    /** @param Collection<int, UntypedDraft> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $draft) {
            $draft->update(['title' => 'Retitled']);
        }
    }
}

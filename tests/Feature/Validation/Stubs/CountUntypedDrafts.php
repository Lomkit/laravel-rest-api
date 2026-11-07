<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;

class CountUntypedDrafts extends Action
{
    /** @param Collection<int, UntypedDraft> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void {}
}

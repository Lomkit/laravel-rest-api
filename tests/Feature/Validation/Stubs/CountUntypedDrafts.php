<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;

class CountUntypedDrafts extends Action
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, UntypedDraft>  $models
     */
    #[\Override]
    public function handle(array $fields, Collection $models): void {}
}

<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;

class RetitleUntypedDrafts extends Action
{
    public bool $targeted = true;

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, UntypedDraft>  $models
     */
    #[\Override]
    public function handle(array $fields, Collection $models): void
    {
        foreach ($models as $draft) {
            $draft->update(['title' => 'Retitled']);
        }
    }
}

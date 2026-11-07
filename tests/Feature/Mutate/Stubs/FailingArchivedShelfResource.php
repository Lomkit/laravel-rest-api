<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Mutations\MutationNode;
use RuntimeException;

class FailingArchivedShelfResource extends ArchivedShelfResource
{
    #[\Override]
    public function mutated(MutationNode $entry, Model $model): void
    {
        if ($model->getAttribute('name') === 'Drama II') {
            throw new RuntimeException('The shelf Drama II cannot be written.');
        }
    }
}

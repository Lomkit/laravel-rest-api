<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\MutateRequest;
use RuntimeException;

class FailingArchivedShelfResource extends ArchivedShelfResource
{
    #[\Override]
    public function mutated(MutateRequest $request, array $requestBody, Model $model): void
    {
        if ($model->getAttribute('name') === 'Drama II') {
            throw new RuntimeException('The shelf Drama II cannot be written.');
        }
    }
}

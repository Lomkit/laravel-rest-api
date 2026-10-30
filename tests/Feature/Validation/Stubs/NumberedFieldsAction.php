<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;

class NumberedFieldsAction extends Action
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Integer::make('rank'), Integer::make('2')];
    }
}

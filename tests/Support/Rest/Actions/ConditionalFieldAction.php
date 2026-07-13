<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;

class ConditionalFieldAction extends Action
{
    public function handle(array $fields, Collection $models)
    {
        //
    }

    public function fields(RestRequest $request): array
    {
        return [
            'type'   => ['nullable', 'string'],
            'reason' => ['required_if:type,ban', 'string'],
            'note'   => ['sometimes', 'string', 'max:5'],
        ];
    }
}

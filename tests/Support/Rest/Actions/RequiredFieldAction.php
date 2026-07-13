<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;

class RequiredFieldAction extends Action
{
    public function handle(array $fields, Collection $models)
    {
        //
    }

    public function fields(RestRequest $request): array
    {
        return [
            'number' => [
                'required',
                'numeric',
                'min:100',
            ],
        ];
    }
}

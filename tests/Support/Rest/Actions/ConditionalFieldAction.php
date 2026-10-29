<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;

class ConditionalFieldAction extends Action
{
    public function handle(array $fields, Collection $models): void
    {
        //
    }

    public function fields(RestRequest $request): array
    {
        return [
            Text::make('type')->nullable(),
            Text::make('reason')->rules('required_if:type,ban'),
            Text::make('note')->rules('sometimes', 'max:5'),
        ];
    }
}

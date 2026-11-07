<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Fields\Text;

class ConditionalFieldAction extends Action
{
    public function handle(ActionFields $fields, Collection $models): void
    {
        //
    }

    public function fields(): array
    {
        return [
            Text::make('type')->nullable(),
            Text::make('reason')->rules('required_if:type,ban'),
            Text::make('note')->rules('sometimes', 'max:5'),
        ];
    }
}

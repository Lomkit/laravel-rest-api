<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Number;

class RequiredFieldAction extends Action
{
    public function handle(array $fields, Collection $models): void
    {
        //
    }

    public function fields(): array
    {
        return [
            Number::make('number')->rules('required', 'min:100'),
        ];
    }
}

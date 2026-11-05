<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Integer;

class NumberedFieldsAction extends Action
{
    #[\Override]
    public function fields(): array
    {
        return [Integer::make('rank'), Integer::make('2')];
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Text;

class HighlightPostsAction extends Action
{
    #[\Override]
    public function fields(): array
    {
        return [Text::make('label')];
    }
}

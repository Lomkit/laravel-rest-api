<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;

class HighlightPostsAction extends Action
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Text::make('label')];
    }
}

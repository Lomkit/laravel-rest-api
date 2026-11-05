<?php

namespace Lomkit\Rest\Tests\Feature\Labels\Stubs;

use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Tests\Fixtures\Publishing\HighlightPosts;

class LabelledHighlightPosts extends HighlightPosts
{
    #[\Override]
    public function fields(): array
    {
        return [
            Boolean::make('featured')->default(true),
            Integer::make('min_views')
                ->nullable()
                ->label('minimum views'),
        ];
    }
}

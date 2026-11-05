<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class MeasuredPostResource extends PostResource
{
    #[\Override]
    public function fields(): array
    {
        return [
            ...parent::fields(),
            Integer::make('title_length')
                ->virtual()
                ->resolveUsing(fn (Model $post): mixed => $post->getAttribute('title_length')),
        ];
    }

    #[\Override]
    public function instructions(): array
    {
        return [TitleLengths::make()];
    }
}

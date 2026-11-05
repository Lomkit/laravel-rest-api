<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class MeasuredPostResource extends PostResource
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ...parent::fields($request),
            Integer::make('title_length')
                ->virtual()
                ->resolveUsing(fn (Model $post): mixed => $post->getAttribute('title_length')),
        ];
    }

    #[\Override]
    public function instructions(RestRequest $request): array
    {
        return [TitleLengths::make()];
    }
}

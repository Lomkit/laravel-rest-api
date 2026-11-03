<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class TagLabelledAuthorResource extends AuthorResource
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ...parent::fields($request),
            Text::make('tags')
                ->virtual()
                ->resolveUsing(fn (Model $author): string => 'none'),
        ];
    }
}

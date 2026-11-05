<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class TagLabelledAuthorResource extends AuthorResource
{
    #[\Override]
    public function fields(): array
    {
        return [
            ...parent::fields(),
            Text::make('tags')
                ->virtual()
                ->resolveUsing(fn (Model $author): string => 'none'),
        ];
    }
}

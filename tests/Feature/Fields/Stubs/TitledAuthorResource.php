<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class TitledAuthorResource extends AuthorResource
{
    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('id')->sortable(false),
            Text::make('first_name')->resolveUsing(fn (Model $author): string => 'Dr '.$author->getAttribute('first_name')),
            Text::make('last_name'),
        ];
    }
}

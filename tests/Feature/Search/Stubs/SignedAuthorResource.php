<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class SignedAuthorResource extends AuthorResource
{
    public static string $response = SignedAuthorResponse::class;

    #[\Override]
    public function fields(): array
    {
        return [
            ...parent::fields(),
            Text::make('signature')
                ->virtual()
                ->requires(['first_name', 'last_name'])
                ->resolveUsing(fn (Model $author): string => $author->getAttribute('first_name').' '.$author->getAttribute('last_name')),
        ];
    }

    #[\Override]
    public function alwaysSelect(): array
    {
        return ['api_token'];
    }
}

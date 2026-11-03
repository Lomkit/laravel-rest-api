<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class SignedAuthorResource extends AuthorResource
{
    public static string $response = SignedAuthorResponse::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ...parent::fields($request),
            Text::make('signature')
                ->virtual()
                ->requires(['first_name', 'last_name'])
                ->resolveUsing(fn (Model $author): string => $author->getAttribute('first_name').' '.$author->getAttribute('last_name')),
        ];
    }

    #[\Override]
    public function alwaysSelect(RestRequest $request): array
    {
        return ['api_token'];
    }
}

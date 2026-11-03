<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class ResolvedAuthorResource extends AuthorResource
{
    /** @var list<mixed> */
    public static array $resolved = [];

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ...parent::fields($request),
            Text::make('display_name')
                ->virtual()
                ->resolveUsing(function (Model $author): string {
                    self::$resolved[] = $author->getKey();

                    return $author->getAttribute('first_name').' '.$author->getAttribute('last_name');
                }),
            Text::make('signature')
                ->virtual()
                ->resolveUsing(fn (Model $author): string => 'signed')
                ->selectable(false),
            Json::make('preferences')
                ->virtual()
                ->resolveUsing(fn (Model $author): string => '{"theme":"dark"}'),
        ];
    }
}

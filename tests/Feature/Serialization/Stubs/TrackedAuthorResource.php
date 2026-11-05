<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class TrackedAuthorResource extends AuthorResource
{
    public static string $model = TrackedAuthor::class;

    #[\Override]
    public function fields(): array
    {
        return [
            ...parent::fields(),
            Text::make('theme')
                ->virtual()
                ->requires(['settings'])
                ->resolveUsing(fn (Model $author): mixed => $author->getAttribute('theme')),
        ];
    }
}

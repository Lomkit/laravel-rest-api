<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class ThemedAuthorResource extends AuthorResource
{
    public static string $model = TrackedAuthor::class;

    #[\Override]
    public function fields(): array
    {
        return [...parent::fields(), Text::make('theme')];
    }
}

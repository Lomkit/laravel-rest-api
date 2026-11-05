<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

abstract class SearchablePostResource extends PostResource
{
    #[\Override]
    public function fields(): array
    {
        return array_map(
            fn (Field $field): Field => in_array($field->name, ['title', 'summary'], true) ? $field->searchable() : $field,
            parent::fields(),
        );
    }
}

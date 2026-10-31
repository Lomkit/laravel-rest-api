<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

class ContainsProductResource extends ProductResource
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return array_map(fn (Field $field): Field => $field->name === 'name' ? $field->searchable() : $field, parent::fields($request));
    }

    #[\Override]
    public function text(RestRequest $request): TextSearch
    {
        return Contains::make();
    }
}

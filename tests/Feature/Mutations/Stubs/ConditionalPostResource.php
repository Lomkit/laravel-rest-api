<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ConditionalPostResource extends PostResource
{
    #[\Override]
    public function rules(): array
    {
        return [
            'summary' => [Rule::when(fn (Fluent $attributes): bool => $attributes->get('featured') === true, ['required'])],
        ];
    }
}

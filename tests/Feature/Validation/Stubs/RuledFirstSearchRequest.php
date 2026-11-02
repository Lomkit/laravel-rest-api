<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Http\Requests\SearchRequest;

class RuledFirstSearchRequest extends SearchRequest
{
    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        return ['search.instructions.*.fields.price' => ['sometimes', 'max:500'], ...parent::rules()];
    }
}

<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Http\Requests\SearchRequest;

class RuledSearchRequest extends SearchRequest
{
    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'search.limit' => ['max:10'],
            'search.flag' => ['boolean'],
            'search.instructions.*.fields.price' => ['sometimes', 'max:500'],
            'search.sorts.*.direction' => ['required'],
        ];
    }
}

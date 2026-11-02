<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Validation\Validator;
use Lomkit\Rest\Http\Requests\SearchRequest;

class EarlyReadingSearchRequest extends SearchRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (): mixed => $this->payload());
    }
}

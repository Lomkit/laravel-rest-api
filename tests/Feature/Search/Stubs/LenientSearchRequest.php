<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Validation\Validator;
use Lomkit\Rest\Http\Requests\SearchRequest;

class LenientSearchRequest extends SearchRequest
{
    #[\Override]
    protected function failedValidation(Validator $validator): void {}
}

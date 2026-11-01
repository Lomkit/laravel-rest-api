<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Validation\Validator;

class FailureIgnoringSearchRequest extends PayloadReadingSearchRequest
{
    #[\Override]
    protected function failedValidation(Validator $validator): void {}
}

<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Validation\Validator;

class FailureIgnoringMutateRequest extends PayloadReadingMutateRequest
{
    #[\Override]
    protected function failedValidation(Validator $validator): void {}
}

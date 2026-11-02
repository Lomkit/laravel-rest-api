<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Validation\Validator;

class FailureIgnoringOperateRequest extends PayloadReadingOperateRequest
{
    #[\Override]
    protected function failedValidation(Validator $validator): void {}
}

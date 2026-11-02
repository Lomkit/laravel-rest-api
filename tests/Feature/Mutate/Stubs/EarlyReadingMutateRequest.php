<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Validation\Validator;
use Lomkit\Rest\Http\Requests\MutateRequest;

class EarlyReadingMutateRequest extends MutateRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (): mixed => $this->payload());
    }
}
